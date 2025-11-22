<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_id',
        'status',
        'role',
        'company_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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

    /**
     * Activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'employee_id', 'status', 'role'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Assign role and permissions based on role field
     */
    public function assignRoleAndPermissions()
    {
        // Remove all current roles
        $this->syncRoles([]);

        // Map role field to Spatie role names
        $roleMapping = [
            'super_admin' => 'Super Admin',
            'system_admin' => 'System Administrator',
            'hr_head' => 'HR Head',
            'hr_staff' => 'HR Staff',
            'employee' => 'Employee',
        ];

        if (isset($roleMapping[$this->role])) {
            $this->assignRole($roleMapping[$this->role]);
        }
    }

    /**
     * Boot method to automatically assign roles when role is changed
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($user) {
            if ($user->wasChanged('role')) {
                $user->assignRoleAndPermissions();
            }
        });
    }

    /**
     * Get the employee associated with the user.
     */
    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Get the company that owns the user.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Check if user is a super admin (can see all companies)
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('Super Admin');
    }

    /**
     * Check if user is a system admin (admin for a specific company or super admin)
     */
    public function isSystemAdmin(): bool
    {
        return $this->hasRole(['Super Admin', 'System Administrator']);
    }
}
