<?php

namespace Zhanghongfei\OrgRbac\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Zhanghongfei\OrgRbac\Enums\TenantType;

class Tenant extends Model
{
    use SoftDeletes;

    /**
     * When {@see parent_id} changes, we stash the old materialized path to rewrite descendant rows after save.
     *
     * @var array<int, string>
     */
    protected static array $orgRbacPathBeforeParentChange = [];

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'domain',
        'type',
        'depth',
        'path',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'depth' => 'integer',
    ];

    public function getTable()
    {
        return config('org-rbac.tables.tenants', parent::getTable());
    }

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant): void {
            if ($tenant->parent_id) {
                /** @var Tenant $parent */
                $parent = static::query()->findOrFail($tenant->parent_id);
                $tenant->depth = $parent->depth + 1;
            } else {
                $tenant->depth = 0;
            }
        });

        static::created(function (Tenant $tenant): void {
            if ($tenant->parent_id) {
                $parent = static::query()->findOrFail($tenant->parent_id);
                $tenant->path = $parent->path.'/'.$tenant->id;
            } else {
                $tenant->path = (string) $tenant->id;
            }
            $tenant->saveQuietly();
        });

        static::updating(function (Tenant $tenant): void {
            if (! $tenant->isDirty('parent_id')) {
                return;
            }

            $oldPath = $tenant->getOriginal('path');
            if ($oldPath === null || $oldPath === '') {
                return;
            }

            if ($tenant->parent_id !== null && (int) $tenant->parent_id === (int) $tenant->id) {
                throw new \InvalidArgumentException('Tenant cannot be its own parent.');
            }

            $parent = $tenant->parent_id !== null
                ? static::query()->find($tenant->parent_id)
                : null;

            if ($tenant->parent_id !== null && $parent === null) {
                throw (new ModelNotFoundException)->setModel(static::class, [$tenant->parent_id]);
            }

            if ($parent !== null && $parent->path !== null && $parent->path !== '') {
                if (str_starts_with($parent->path.'/', $oldPath.'/')) {
                    throw new \InvalidArgumentException('Cannot move a tenant under its own descendant.');
                }
            }

            $tenant->depth = $parent ? $parent->depth + 1 : 0;
            $tenant->path = $parent
                ? $parent->path.'/'.$tenant->id
                : (string) $tenant->id;

            static::$orgRbacPathBeforeParentChange[$tenant->getKey()] = $oldPath;
        });

        static::updated(function (Tenant $tenant): void {
            $id = $tenant->getKey();
            if (! isset(static::$orgRbacPathBeforeParentChange[$id])) {
                return;
            }

            $oldPath = static::$orgRbacPathBeforeParentChange[$id];
            unset(static::$orgRbacPathBeforeParentChange[$id]);

            $newPath = $tenant->path;
            if ($newPath === null || $oldPath === $newPath) {
                return;
            }

            static::query()
                ->where('path', 'like', $oldPath.'/%')
                ->orderBy('depth')
                ->chunkById(200, function (Collection $rows) use ($oldPath, $newPath): void {
                    foreach ($rows as $desc) {
                        /** @var Tenant $desc */
                        $desc->path = $newPath . substr($desc->path, strlen($oldPath));
                        $desc->depth = substr_count($desc->path, '/');
                        $desc->saveQuietly();
                    }
                });
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(config('org-rbac.models.role'), 'tenant_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(config('org-rbac.models.permission'), 'tenant_id');
    }

    /**
     * Membership pivot (user ids) — use {@see self::members()} with your User model.
     */
    public function tenantUserPivotTable(): string
    {
        return config('org-rbac.tables.tenant_user', 'org_rbac_tenant_user');
    }

    /**
     * @param  class-string<\Illuminate\Foundation\Auth\User>  $userModel
     */
    public function members(string $userModel): BelongsToMany
    {
        return $this->belongsToMany($userModel, $this->tenantUserPivotTable())
            ->withPivot(['is_owner', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * All descendant tenants — typically one indexed query on `path`.
     *
     * @return Collection<int, Tenant>
     */
    public function descendants(): Collection
    {
        if (! $this->path) {
            return new Collection;
        }

        return static::query()
            ->where('path', 'like', $this->path.'/%')
            ->orderBy('depth')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Tenant>
     */
    public function ancestors(): Collection
    {
        if (! $this->path) {
            return new Collection;
        }

        $ids = collect(explode('/', $this->path))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id !== $this->id);

        return static::query()
            ->whereIn('id', $ids)
            ->orderBy('depth')
            ->get();
    }

    /**
     * @return Collection<int, Tenant>
     */
    public function breadcrumb(): Collection
    {
        return $this->ancestors()->push($this);
    }

    /**
     * @return Collection<int, Tenant>
     */
    public function siblings(): Collection
    {
        return static::query()
            ->where('parent_id', $this->parent_id)
            ->where('id', '!=', $this->id)
            ->get();
    }

    /**
     * @return list<int>
     */
    public function subtreeIds(): array
    {
        return $this->descendants()
            ->pluck('id')
            ->prepend($this->id)
            ->unique()
            ->values()
            ->all();
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function isLeaf(): bool
    {
        return $this->children()->doesntExist();
    }

    public function isDescendantOf(Tenant $tenant): bool
    {
        if (! $this->path || ! $tenant->path) {
            return false;
        }

        return str_starts_with($this->path.'/', $tenant->path.'/');
    }

    public function isAncestorOf(Tenant $tenant): bool
    {
        return $tenant->isDescendantOf($this);
    }

    public function isSameOrDescendantOf(Tenant $tenant): bool
    {
        return $this->id === $tenant->id || $this->isDescendantOf($tenant);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, TenantType|string $type)
    {
        $value = $type instanceof TenantType ? $type->value : $type;

        return $query->where('type', $value);
    }

    public function scopeWithinSubtree($query, Tenant $tenant)
    {
        return $query->where(function ($q) use ($tenant) {
            $q->where('id', $tenant->id)
                ->orWhere('path', 'like', $tenant->path.'/%');
        });
    }

    /**
     * Tree root node (first id in materialized path), for data-scope "whole org" fallbacks.
     */
    public function rootAncestor(): self
    {
        if (! $this->path) {
            return $this;
        }

        $ids = explode('/', $this->path);
        $rootId = (int) ($ids[0] ?? $this->id);

        return $rootId === (int) $this->id
            ? $this
            : static::query()->findOrFail($rootId);
    }

    /**
     * Nearest ancestor (or self) with type `organisation` — used for full-org data scope on business rows.
     */
    public function nearestOrganisationAncestor(): ?self
    {
        foreach ($this->breadcrumb() as $node) {
            if (($node->type ?? '') === TenantType::Organisation->value) {
                return $node;
            }
        }

        return null;
    }
}
