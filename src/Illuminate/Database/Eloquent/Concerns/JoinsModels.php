<?php

declare(strict_types=1);

namespace Illuminate\Database\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Str;

trait JoinsModels
{
    /**
     * @param class-string<Model>|Model|Builder<Model> $model
     * @param string $joinType
     * @param string|null $overrideJoinColumnName
     * @return static
     */
    public function joinMany($model, string $joinType = 'inner', ?string $overrideJoinColumnName = null, ?string $tableAlias = null): static  {
        /** @var Builder $builder */
        $builder = match(true) {
            is_string($model) => (new $model())->newQuery(),
            $model instanceof Builder => $model,
            $model instanceof Model => $model->newQuery(),
            $model instanceof Relation => $model->getQuery(),
        };

        return $this->joinManyOn($this->getModel(), $builder, $joinType,null, $overrideJoinColumnName, $tableAlias);
    }

    /**
     * @param class-string|Model|Builder<Model> $model
     * @param string $joinType
     * @param string|null $overrideBaseColumn
     * @return static
     */
    public function joinOne($model, string $joinType = 'inner', ?string $overrideBaseColumn = null, ?string $tableAlias = null): static {
        $builder = match(true) {
            is_string($model) => (new $model())->newQuery(),
            $model instanceof Builder => $model,
            $model instanceof Model => $model->newQuery(),
            $model instanceof Relation => $model->getQuery(),
        };

        $this->joinOneOn($this->getModel(), $builder, $joinType, $overrideBaseColumn, null, $tableAlias);

        return $this;
    }


    private function joinManyOn(Model $baseModel, Builder $builderToJoin, ?string $joinType = 'inner', ?string $overrideBaseColumnName = null, ?string $overrideJoinColumnName = null, ?string $tableAlias = null): static
    {
        $modelToJoin = $builderToJoin->getModel();
        $aliasToUse = $tableAlias ? ($modelToJoin->getTable() . ' as ' . $tableAlias) : $modelToJoin->getTable();
        if($tableAlias){
            // override table name to properly qualify table names.
            // todo decide if need to reset after join to avoid weird side effects.
            $modelToJoin->setTable($tableAlias);
        }
        $manyJoinColumnName = $overrideJoinColumnName ?? (Str::singular($baseModel->getTable()). '_' . $baseModel->getKeyName());
        $baseColumnName = $overrideBaseColumnName ?? $baseModel->getKeyName();
        $this->join(
            $aliasToUse, fn(JoinClause $join) =>
                $join->on(
                    $modelToJoin->qualifyColumn($manyJoinColumnName),
                    '=',
                    $baseModel->qualifyColumn($baseColumnName),
                )->addNestedWhereQuery($builderToJoin->applyScopes()->getQuery()),
            type: $joinType
        );

        return $this;
    }

    private function joinOneOn(Model $baseModel, Builder $builderToJoin, string $joinType = 'inner', string $overrideBaseColumnName = null, string $overrideJoinColumnName = null, ?string $tableAlias = null): static
    {
        $modelToJoin = $builderToJoin->getModel();
        $aliasToUse = $tableAlias ? ($modelToJoin->getTable() . ' as ' . $tableAlias) : $modelToJoin->getTable();
        if($tableAlias){
            // override table name to properly qualify table names.
            // todo decide if need to reset after join to avoid weird side effects.
            $modelToJoin->setTable($tableAlias);
        }
        $joinColumnName = $overrideBaseColumnName ?? $modelToJoin->getKeyName();
        $baseColumnName = $overrideJoinColumnName ?? (Str::singular($modelToJoin->getTable()). '_' . $modelToJoin->getKeyName());
        $this->join(
            $aliasToUse, fn(JoinClause $join) =>
                $join->on(
                    $modelToJoin->qualifyColumn($joinColumnName),
                    '=',
                    $baseModel->qualifyColumn($baseColumnName),
                )->addNestedWhereQuery($builderToJoin->getQuery()),
            type: $joinType
        );
        $this->applyScopesWith($builderToJoin->getScopes(), $modelToJoin);
        return $this;
    }

    public function joinRelation(string $relation, string $joinType = 'inner', bool $aliasAsRelations = false): static
    {
        // todo make it work with relationName.deeperRelationName.
        $relationClass = Relation::noConstraints(fn()=> $this->getModel()->$relation());
        if($relationClass instanceof HasOneOrMany){
            // todo ponder if this should be done in relationship class (seems like the right place).
            return $this->joinManyOn($this->getModel(), $relationClass->getQuery(), $joinType, $relationClass->getQualifiedParentKeyName(),$relationClass->getForeignKeyName(), $aliasAsRelations ? $relation : null);
        } elseif($relationClass instanceof BelongsTo){
            return $this->joinOneOn($this->getModel(), $relationClass->getQuery(), $joinType, null, $relationClass->getForeignKeyName(), $aliasAsRelations ? $relation : null);

        }
        return $this;
    }

    /**
     * @param Scope[] $scopes
     * @param Model $model
     * @return static
     */
    private function applyScopesWith(array $scopes, Model $model): static
    {
        foreach($scopes as $scope){
            $scope->apply($this, $model);
        }
        return $this;
    }
}
