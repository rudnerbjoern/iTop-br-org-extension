<?php

declare(strict_types=1);

namespace OrganizationTests;

// Execute trusted local model methods; substitutes cover only their iTop calls.
$model = simplexml_load_file(__DIR__ . '/../br-org-extension/datamodel.br-org-extension.xml', \SimpleXMLElement::class, LIBXML_NONET);
if ($model === false) {
    throw new \RuntimeException('Cannot load Organization data model.');
}
$code = '';
foreach (['SetNicename', 'UpdateChildren', 'OnOrganizationBeforeWriteNicename', 'OnOrganizationAfterWriteNicename'] as $method) {
    $nodes = $model->xpath('/itop_design/classes/class[@id="Organization"]/methods/method[@id="' . $method . '"]/code');
    if ($nodes === false || count($nodes) !== 1) {
        throw new \RuntimeException('Expected exactly one Organization method: ' . $method);
    }
    $code .= (string) $nodes[0];
}
eval('namespace OrganizationTests; trait ModelMethods {' . $code . '}');

class EventData
{
    public function __construct(private array $values = []) {}
    public function Get(string $key) { return $this->values[$key] ?? null; }
}
class_alias(EventData::class, __NAMESPACE__ . '\\Combodo\iTop\Service\Events\EventData');

class MetaModel
{
    public static array $organizations = [];
    public static function GetObject(string $class, int $id): ?Organization
    {
        if ($class !== 'Organization') {
            throw new \RuntimeException('Unexpected parent class.');
        }
        return self::$organizations[$id] ?? null;
    }
}

class ChildSet
{
    private int $position = 0;
    public function __construct(private array $children) {}
    public function Fetch(): ?Organization { return $this->children[$this->position++] ?? null; }
}

class Organization
{
    use ModelMethods;

    public $i_NameChanged;
    public array $changes = [];
    public int $writes = 0;
    public function __construct(private array $values) {}
    public function Get(string $key) { return $this->values[$key] ?? null; }
    public function Set(string $key, $value): void { $this->values[$key] = $value; }
    public function ListChanges(): array { return $this->changes; }
    public function DBUpdate(): void { $this->writes++; }
}

function expect($actual, $expected): void
{
    if ($actual !== $expected) {
        throw new \RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

$tests = [
    'new root organization uses its own name' => static function (): void {
        $org = new Organization(['name' => 'Company', 'parent_id' => 0]);
        $org->OnOrganizationBeforeWriteNicename(new EventData(['is_new' => true]));
        expect($org->Get('nicename'), 'Company');
    },
    'child name includes the full parent path' => static function (): void {
        MetaModel::$organizations = [1 => new Organization(['nicename' => 'Company/Division'])];
        $org = new Organization(['name' => 'Team', 'parent_id' => 1]);
        $org->SetNicename();
        expect($org->Get('nicename'), 'Company/Division/Team');
    },
    'unrelated edits preserve the display name and leave children untouched' => static function (): void {
        $child = new Organization(['nicename' => 'Existing/Child']);
        $org = new Organization(['name' => 'Company', 'nicename' => 'Existing', 'organizations_list' => new ChildSet([$child])]);
        $org->changes = ['description' => 'Updated'];
        $org->OnOrganizationBeforeWriteNicename(new EventData());
        $org->OnOrganizationAfterWriteNicename(new EventData());
        expect($org->Get('nicename'), 'Existing');
        expect($child->writes, 0);
    },
    'renaming a parent cascades through multiple child levels' => static function (): void {
        $leaf = new Organization(['name' => 'Team', 'parent_id' => 2]);
        $child = new Organization(['name' => 'Division', 'parent_id' => 1, 'organizations_list' => new ChildSet([$leaf])]);
        $org = new Organization(['name' => 'Renamed', 'parent_id' => 0, 'organizations_list' => new ChildSet([$child])]);
        MetaModel::$organizations = [1 => $org, 2 => $child];
        $org->changes = ['name' => 'Company'];
        $org->OnOrganizationBeforeWriteNicename(new EventData());
        $org->OnOrganizationAfterWriteNicename(new EventData());
        expect($child->Get('nicename'), 'Renamed/Division');
        expect($leaf->Get('nicename'), 'Renamed/Division/Team');
        expect($child->writes, 1);
        expect($leaf->writes, 1);
    },
];
foreach ([1 => 'Other/Team', 0 => 'Team'] as $parent => $expected) {
    $tests['moving an organization to parent ' . $parent . ' rebuilds its name'] = static function () use ($parent, $expected): void {
        MetaModel::$organizations = [1 => new Organization(['nicename' => 'Other'])];
        $org = new Organization(['name' => 'Team', 'parent_id' => $parent, 'nicename' => 'Old/Team']);
        $org->changes = ['parent_id' => 9];
        $org->OnOrganizationBeforeWriteNicename(new EventData());
        expect($org->Get('nicename'), $expected);
        expect($org->i_NameChanged, true);
    };
}

return $tests;
