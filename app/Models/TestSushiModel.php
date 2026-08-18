<?php

declare(strict_types=1);

namespace Modules\Tenant\Models;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Modules\Tenant\Actions\Config\GetTenantFilePathAction;
use Modules\Tenant\Database\Factories\TestSushiModelFactory;
use Modules\Tenant\Models\Traits\SushiToJson;
use Modules\Xot\Models\Traits\HasXotFactory;

class TestSushiModel extends BaseModel
{
    /** @phpstan-use HasXotFactory<TestSushiModelFactory> */
    use HasXotFactory;

    use SushiToJson;

    /**
     * @var array<string, string>
     */
    protected array $schema = [
        'id' => 'integer',
        'name' => 'string',
        'description' => 'string',
        'status' => 'string',
        'metadata' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    protected $table = 'test_sushi';

    protected $fillable = [
        'name',
        'description',
        'status',
        'metadata',
        'created_by',
        'updated_by',
    ];

    public function getJsonFile(): string
    {
        if (app()->environment('testing')) {
            $dir = storage_path('tests/sushi-json');
            if (! File::exists($dir)) {
                File::makeDirectory($dir, 0o755, true, true);
            }

            return $dir.'/test_sushi.json';
        }

        // fallback: usa il comportamento del trait (replicato qui)
        $tbl = $this->getTable();
        $filePath = app(GetTenantFilePathAction::class)->execute('database/content/'.$tbl.'.json');
        if (! is_string($filePath)) {
            throw new InvalidArgumentException('File path must be string');
        }

        return $filePath;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRows(): array
    {
        return $this->getSushiRows();
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }
}
