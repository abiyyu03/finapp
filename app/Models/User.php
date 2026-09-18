<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return HasMany<CompanyMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyMembership::class);
    }

    /**
     * Companies this user is a member of (spec §4: a user may belong to
     * several companies with a different role in each).
     *
     * @return BelongsToMany<Company, $this>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_memberships')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function roleFor(Company $company): ?Role
    {
        return $this->memberships
            ->firstWhere('company_id', $company->id)
            ?->role;
    }

    /**
     * The company selected in the current session (spec §16 / STEP 2).
     * Every financial query elsewhere scopes to this, never to a client-sent
     * company_id.
     */
    public function activeCompany(): ?Company
    {
        $id = session('active_company_id');

        return $id ? $this->companies()->find($id) : null;
    }

    /**
     * Whether this user's role IN THE ACTIVE COMPANY carries the given
     * permission key (spec §8: Company Membership → Role → Permission →
     * Action). No active company means no permission.
     */
    public function hasPermission(string $key): bool
    {
        $company = $this->activeCompany();

        if (! $company) {
            return false;
        }

        return $this->roleFor($company)?->permissions->contains('key', $key) ?? false;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
