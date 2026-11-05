<?php

namespace App\Models;

use App\Enums\ClassColor;
use Database\Factories\ClassTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property ClassColor $color
 * @property int $default_capacity
 * @property int $default_duration_minutes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description', 'color', 'default_capacity', 'default_duration_minutes'])]
class ClassType extends Model
{
    /** @use HasFactory<ClassTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => ClassColor::class,
            'default_capacity' => 'integer',
            'default_duration_minutes' => 'integer',
        ];
    }

    /**
     * @return HasMany<Slot, $this>
     */
    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class);
    }
}
