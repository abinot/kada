<?php

use App\Models\Project;
use App\Models\Record;
use App\Models\RecordValue;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);
    $this->user->update(['current_team_id' => $this->team->id]);

    $this->project = Project::create([
        'team_id' => $this->team->id,
        'name' => 'فهرست نیازمندان',
    ]);

    $this->actingAs($this->user);
});

// ---------- ساخت رکورد ----------

it('creates a record with uuid', function () {
    $record = Record::create(['title' => 'رضا بهرامیان']);

    expect($record)->toBeInstanceOf(Record::class);
    expect($record->uuid)->not->toBeEmpty();
    expect($record->title)->toBe('رضا بهرامیان');
});

it('attaches a record to a project via pivot', function () {
    $record = Record::create(['title' => 'تست']);
    $record->projects()->attach($this->project->id);

    expect($record->fresh()->projects)->toHaveCount(1);
    expect($this->project->fresh()->records)->toHaveCount(1);
});

it('attaches the same record to multiple projects', function () {
    $project2 = Project::create([
        'team_id' => $this->team->id,
        'name' => 'فهرست خیرین',
    ]);

    $record = Record::create(['title' => 'مشترک']);
    $record->projects()->attach([$this->project->id, $project2->id]);

    expect($record->fresh()->projects)->toHaveCount(2);
});

it('stores created_by automatically from authenticated user', function () {
    $record = Record::create(['title' => 'تست']);

    expect($record->created_by)->toBe($this->user->id);
});

// ---------- ذخیره‌ی مقادیر ساده ----------

it('stores a simple string value', function () {
    $record = Record::create();
    $record->syncValues(['name' => 'علی رضایی']);

    expect($record->fresh()->getValue('name'))->toBe('علی رضایی');
});

it('stores an integer value typed', function () {
    $record = Record::create();
    $record->syncValues(['age' => 30]);

    $value = $record->values()->where('key', 'age')->first();

    expect($value->value_type)->toBe('integer');
    expect($value->value_number)->not->toBeNull();
    expect($record->fresh()->getValue('age'))->toBe(30);
});

it('stores a boolean value typed', function () {
    $record = Record::create();
    $record->syncValues(['is_verified' => true]);

    $value = $record->values()->where('key', 'is_verified')->first();

    expect($value->value_type)->toBe('boolean');
    expect($value->value_boolean)->toBeTrue();
    expect($record->fresh()->getValue('is_verified'))->toBeTrue();
});

it('stores a decimal value typed', function () {
    $record = Record::create();
    $record->syncValues(['amount' => 1500.75]);

    $value = $record->values()->where('key', 'amount')->first();

    expect($value->value_type)->toBe('decimal');
    expect((float) $value->value_number)->toBe(1500.75);
});

// ---------- ذخیره‌ی مقادیر تودرتو ----------

it('stores a nested value tree', function () {
    $record = Record::create();
    $record->syncValues([
        'name' => [
            'value' => 'رضا بهرامیان',
            'prefix_name' => 'دکتر',
            'mean_of_name' => 'رضا نام هشتمین امام مسلمین است',
        ],
    ]);

    $nameRoot = $record->values()->whereNull('parent_id')->where('key', 'name')->first();

    expect($nameRoot)->not->toBeNull();
    expect($nameRoot->value_string)->toBe('رضا بهرامیان');
    expect($nameRoot->children()->count())->toBe(2);
    expect($nameRoot->children()->where('key', 'prefix_name')->first()->value_string)->toBe('دکتر');
});

it('reads a nested value with dot notation', function () {
    $record = Record::create();
    $record->syncValues([
        'name' => [
            'value' => 'رضا',
            'prefix_name' => 'دکتر',
        ],
    ]);

    expect($record->fresh()->getValue('name'))->toBe('رضا');
    expect($record->fresh()->getValue('name.prefix_name'))->toBe('دکتر');
});

it('returns nested data as array', function () {
    $record = Record::create();
    $record->syncValues([
        'name' => [
            'value' => 'رضا',
            'prefix_name' => 'دکتر',
        ],
        'age' => 30,
    ]);

    $data = $record->fresh()->data;

    expect($data)->toBeArray();
    expect($data['name'])->toBeArray();
    expect($data['name']['value'])->toBe('رضا');
    expect($data['name']['prefix_name'])->toBe('دکتر');
    expect($data['age'])->toBe(30);
});

it('returns flat data with dot-path keys', function () {
    $record = Record::create();
    $record->syncValues([
        'name' => [
            'value' => 'رضا',
            'prefix_name' => 'دکتر',
        ],
        'age' => 30,
    ]);

    $flat = $record->fresh()->flatData;

    expect($flat)->toHaveKey('name.prefix_name');
    expect($flat['name.prefix_name'])->toBe('دکتر');
    expect($flat)->toHaveKey('age');
    expect($flat['age'])->toBe(30);
});

it('updates an existing value overwriting old one', function () {
    $record = Record::create();
    $record->syncValues(['name' => 'علی']);

    $record->syncValues(['name' => 'حسن']);

    expect($record->fresh()->getValue('name'))->toBe('حسن');
    expect($record->values()->where('key', 'name')->count())->toBe(1);
});

it('deletes a nested value with its children', function () {
    $record = Record::create();
    $record->syncValues([
        'name' => [
            'value' => 'رضا',
            'prefix_name' => 'دکتر',
        ],
    ]);

    expect(RecordValue::count())->toBe(2);

    $record->removeValue('name');

    expect(RecordValue::count())->toBe(0);
});

it('returns empty data for a record without values', function () {
    $record = Record::create();

    expect($record->data)->toBe([]);
});

// ---------- setValue با مسیر ----------

it('sets a value with dot path creating intermediate nodes', function () {
    $record = Record::create();

    $record->setValue('contact.phone', '09120000000');

    expect($record->fresh()->getValue('contact.phone'))->toBe('09120000000');
    expect($record->values()->where('key', 'contact')->exists())->toBeTrue();
});

// ---------- روابط پروژه ----------

it('returns used keys of a project', function () {
    $record = Record::create();
    $record->projects()->attach($this->project->id);
    $record->syncValues(['name' => 'علی', 'phone' => '0912']);

    $record2 = Record::create();
    $record2->projects()->attach($this->project->id);
    $record2->syncValues(['name' => 'حسن', 'age' => 30]);

    $keys = $this->project->fresh()->usedKeys();

    expect($keys)->toContain('name', 'phone', 'age');
});

it('returns key frequency for a project', function () {
    foreach (range(1, 3) as $i) {
        $r = Record::create();
        $r->projects()->attach($this->project->id);
        $r->syncValues(['name' => "Person $i"]);
    }

    foreach (range(1, 1) as $i) {
        $r = Record::create();
        $r->projects()->attach($this->project->id);
        $r->syncValues(['phone' => '0912']);
    }

    $freq = $this->project->fresh()->keyFrequency();

    expect($freq['name'])->toBe(3);
    expect($freq['phone'])->toBe(1);
});

it('only counts values from records attached to the project', function () {
    $project2 = Project::create(['team_id' => $this->team->id, 'name' => 'پروژه دوم']);

    $r1 = Record::create();
    $r1->projects()->attach($this->project->id);
    $r1->syncValues(['name' => 'X']);

    $r2 = Record::create();
    $r2->projects()->attach($project2->id);
    $r2->syncValues(['name' => 'Y']);

    $freq = $this->project->fresh()->keyFrequency();

    expect($freq)->toHaveKey('name');
    expect($freq['name'])->toBe(1);
});

// ---------- حذف ----------

it('soft deletes a record', function () {
    $record = Record::create();
    $record->projects()->attach($this->project->id);

    $record->delete();

    expect(Record::count())->toBe(0);
    expect(Record::withTrashed()->count())->toBe(1);
});

it('cascades delete to values when record is force deleted', function () {
    $record = Record::create();
    $record->projects()->attach($this->project->id);
    $record->syncValues(['name' => 'علی', 'age' => 30]);

    expect(RecordValue::count())->toBe(2);

    $record->forceDelete();

    expect(RecordValue::count())->toBe(0);
});

it('detaches a record from one project keeping it in another', function () {
    $project2 = Project::create(['team_id' => $this->team->id, 'name' => 'خیرین']);

    $record = Record::create();
    $record->projects()->attach([$this->project->id, $project2->id]);

    $record->projects()->detach($this->project->id);

    expect($record->fresh()->projects)->toHaveCount(1);
    expect($record->projects->first()->id)->toBe($project2->id);
});

it('deletes pivot when record is deleted', function () {
    $record = Record::create();
    $record->projects()->attach($this->project->id);

    $record->forceDelete();

    expect($this->project->fresh()->records)->toHaveCount(0);
});

// ---------- ساختار درخت RecordValue ----------

it('calculates depth of a node', function () {
    $record = Record::create();
    $record->syncValues([
        'a' => [
            'value' => '1',
            'b' => [
                'value' => '2',
                'c' => '3',
            ],
        ],
    ]);

    $a = $record->values()->where('key', 'a')->first();
    $b = $a->children()->where('key', 'b')->first();
    $c = $b->children()->where('key', 'c')->first();

    expect($a->depth())->toBe(0);
    expect($b->depth())->toBe(1);
    expect($c->depth())->toBe(2);
});

it('calculates full path of a node', function () {
    $record = Record::create();
    $record->syncValues([
        'contact' => [
            'value' => 'X',
            'phone' => '0912',
        ],
    ]);

    $phone = $record->values()->where('key', 'phone')->first();

    expect($phone->fullPath())->toBe('contact.phone');
});

it('detects leaf and root nodes', function () {
    $record = Record::create();
    $record->syncValues([
        'name' => ['value' => 'A', 'prefix' => 'B'],
    ]);

    $root = $record->values()->whereNull('parent_id')->first();
    $leaf = $record->values()->where('key', 'prefix')->first();

    expect($root->isRoot())->toBeTrue();
    expect($root->isLeaf())->toBeFalse();
    expect($leaf->isRoot())->toBeFalse();
    expect($leaf->isLeaf())->toBeTrue();
});

it('returns toArrayTree with nested children', function () {
    $record = Record::create();
    $record->syncValues([
        'name' => [
            'value' => 'رضا',
            'prefix_name' => 'دکتر',
        ],
    ]);

    $root = $record->values()->whereNull('parent_id')->first();
    $tree = $root->toArrayTree();

    expect($tree['key'])->toBe('name');
    expect($tree['value'])->toBe('رضا');
    expect($tree['children'])->toHaveCount(1);
    expect($tree['children'][0]['key'])->toBe('prefix_name');
});

// ---------- یکتایی کلید زیر یک والد ----------

it('allows same key under different parents', function () {
    $record = Record::create();
    $record->syncValues([
        'parent_a' => ['value' => 'A', 'name' => 'one'],
        'parent_b' => ['value' => 'B', 'name' => 'two'],
    ]);

    expect($record->values()->where('key', 'name')->count())->toBe(2);
    expect($record->fresh()->getValue('parent_a.name'))->toBe('one');
    expect($record->fresh()->getValue('parent_b.name'))->toBe('two');
});

it('overwrites value when same key under same parent', function () {
    $record = Record::create();
    $record->syncValues(['name' => 'first']);
    $record->syncValues(['name' => 'second']);

    expect($record->values()->where('key', 'name')->count())->toBe(1);
    expect($record->fresh()->getValue('name'))->toBe('second');
});

// ---------- حذف مقدار خالی ----------

it('removes value when set to empty string', function () {
    $record = Record::create();
    $record->syncValues(['name' => 'علی']);

    $record->syncValues(['name' => '']);

    expect($record->values()->where('key', 'name')->count())->toBe(0);
});

it('removes value when set to null', function () {
    $record = Record::create();
    $record->syncValues(['name' => 'علی']);

    $record->syncValues(['name' => null]);

    expect($record->values()->where('key', 'name')->count())->toBe(0);
});