<?php

namespace Modules\HR\Tests\Unit;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Modules\HR\Enums\CommissionTypeEnum;
use Modules\HR\Enums\ContractTypeEnum;
use Modules\HR\Http\Requests\CommissionTemplate\StoreCommissionTemplateRequest;
use Modules\HR\Http\Requests\CommissionTemplate\UpdateCommissionTemplateRequest;
use Modules\HR\Http\Requests\Contract\StoreStaffContractRequest;
use Modules\HR\Http\Requests\Contract\UpdateStaffContractRequest;
use Modules\HR\Models\CommissionTemplate;
use Modules\HR\Models\StaffContract;
use Modules\HR\Services\CommissionTemplate\CommissionTemplateService;
use Modules\HR\Services\Contract\ContractService;
use Modules\Setup\Enums\ServiceTypeEnum;
use Modules\Setup\Models\Department;
use Modules\Setup\Models\Service;
use Tests\TestCase;

class CommissionTemplateTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $doctor;
    protected Department $department;
    protected Service $service1;
    protected Service $service2;
    protected Service $service3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'admin_test_comm@example.com'],
            [
                'name' => 'Admin Test Comm',
                'password' => bcrypt('password'),
                'type' => UserType::ADMIN->value,
                'phone' => '01011112222',
            ]
        );

        $this->doctor = User::firstOrCreate(
            ['email' => 'doctor_test_comm@example.com'],
            [
                'name' => 'Dr Test Comm',
                'password' => bcrypt('password'),
                'type' => UserType::DOCTOR->value,
                'phone' => '01033334444',
            ]
        );

        $this->department = Department::firstOrCreate(
            ['name' => 'Dermatology & Laser'],
            ['description' => 'Test Department']
        );

        $this->service1 = Service::create([
            'department_id' => $this->department->id,
            'name' => 'Full Face Laser',
            'type' => ServiceTypeEnum::DEVICE->value,
            'price' => 500,
            'cost' => 50,
            'is_active' => true,
        ]);

        $this->service2 = Service::create([
            'department_id' => $this->department->id,
            'name' => 'Derma Pen Session',
            'type' => ServiceTypeEnum::SESSION->value,
            'price' => 300,
            'cost' => 30,
            'is_active' => true,
        ]);

        $this->service3 = Service::create([
            'department_id' => $this->department->id,
            'name' => 'Botox Injection',
            'type' => ServiceTypeEnum::SESSION->value,
            'price' => 1200,
            'cost' => 200,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin, 'api');
    }

    protected function makeRequest(string $class, array $data, string $method = 'POST')
    {
        $request = $class::create('/', $method, $data);
        $request->setContainer(app());
        $request->setRedirector(app(\Illuminate\Routing\Redirector::class));
        $request->setUserResolver(fn () => $this->admin);
        $request->validateResolved();
        return $request;
    }

    public function test_can_create_commission_template_with_items()
    {
        $service = app(CommissionTemplateService::class);

        $request = $this->makeRequest(StoreCommissionTemplateRequest::class, [
            'name' => 'لائحة عمولات الليزر والتجميل القياسية',
            'description' => 'اللائحة الافتراضية لأطباء الجلدية والتجميل',
            'department_id' => $this->department->id,
            'is_active' => true,
            'items' => [
                [
                    'service_id' => $this->service1->id,
                    'doctor_service_price' => 250.00,
                    'is_laser' => true,
                    'commission_type' => CommissionTypeEnum::PERCENTAGE->value,
                    'commission_value' => 15.00,
                ],
                [
                    'service_id' => $this->service2->id,
                    'doctor_service_price' => 150.00,
                    'is_laser' => false,
                    'commission_type' => CommissionTypeEnum::FIXED->value,
                    'commission_value' => 50.00,
                ],
            ],
        ]);

        $response = $service->store($request);
        $data = json_decode($response->getContent(), true)['data'];

        $this->assertDatabaseHas('commission_templates', [
            'name' => 'لائحة عمولات الليزر والتجميل القياسية',
            'department_id' => $this->department->id,
        ]);

        $this->assertDatabaseHas('commission_template_items', [
            'service_id' => $this->service1->id,
            'commission_type' => CommissionTypeEnum::PERCENTAGE->value,
            'commission_value' => 15.00,
        ]);

        $this->assertDatabaseHas('commission_template_items', [
            'service_id' => $this->service2->id,
            'commission_type' => CommissionTypeEnum::FIXED->value,
            'commission_value' => 50.00,
        ]);

        $this->assertCount(2, $data['items']);
    }

    public function test_can_update_commission_template()
    {
        $template = CommissionTemplate::create([
            'name' => 'Old Template Name',
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);

        $template->items()->create([
            'service_id' => $this->service1->id,
            'commission_type' => CommissionTypeEnum::PERCENTAGE->value,
            'commission_value' => 10.00,
        ]);

        $service = app(CommissionTemplateService::class);
        $request = $this->makeRequest(UpdateCommissionTemplateRequest::class, [
            'name' => 'Updated Template Name',
            'items' => [
                [
                    'service_id' => $this->service3->id,
                    'doctor_service_price' => 500.00,
                    'is_laser' => false,
                    'commission_type' => CommissionTypeEnum::PERCENTAGE->value,
                    'commission_value' => 20.00,
                ],
            ],
        ], 'PUT');

        $service->update($template->id, $request);

        $this->assertDatabaseHas('commission_templates', [
            'id' => $template->id,
            'name' => 'Updated Template Name',
        ]);

        $this->assertDatabaseHas('commission_template_items', [
            'commission_template_id' => $template->id,
            'service_id' => $this->service3->id,
            'commission_value' => 20.00,
        ]);

        $this->assertDatabaseMissing('commission_template_items', [
            'commission_template_id' => $template->id,
            'service_id' => $this->service1->id,
        ]);
    }

    public function test_creating_contract_with_commission_template_id_clones_all_items()
    {
        $template = CommissionTemplate::create([
            'name' => 'قالب عمولات الدكتور',
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);

        $template->items()->create([
            'service_id' => $this->service1->id,
            'doctor_service_price' => 50.00,
            'is_laser' => true,
            'commission_type' => CommissionTypeEnum::PERCENTAGE->value,
            'commission_value' => 10.00,
        ]);

        $template->items()->create([
            'service_id' => $this->service2->id,
            'doctor_service_price' => 100.00,
            'is_laser' => true,
            'commission_type' => CommissionTypeEnum::PERCENTAGE->value,
            'commission_value' => 12.00,
        ]);

        $contractService = app(ContractService::class);
        $request = $this->makeRequest(StoreStaffContractRequest::class, [
            'user_id' => $this->doctor->id,
            'title' => 'عقد أخصائي جلدية وتجميل وليزر',
            'contract_type' => ContractTypeEnum::DOCTOR->value,
            'is_active' => true,
            'hourly_rate' => 40.00,
            'has_target' => true,
            'target_amount' => 7500.00,
            'commission_template_id' => $template->id,
            // Notice: NO service_commissions array provided!
        ]);

        $response = $contractService->store($request);
        $data = json_decode($response->getContent(), true)['data'];

        $this->assertDatabaseHas('staff_contracts', [
            'user_id' => $this->doctor->id,
            'commission_template_id' => $template->id,
        ]);

        $contract = StaffContract::with('serviceCommissions')->find($data['id']);
        $this->assertCount(2, $contract->serviceCommissions);

        $comm1 = $contract->serviceCommissions->firstWhere('service_id', $this->service1->id);
        $this->assertNotNull($comm1);
        $this->assertEquals(50.00, (float) $comm1->doctor_service_price);
        $this->assertTrue((bool) $comm1->is_laser);
        $this->assertEquals(10.00, (float) $comm1->commission_value);

        $comm2 = $contract->serviceCommissions->firstWhere('service_id', $this->service2->id);
        $this->assertNotNull($comm2);
        $this->assertEquals(100.00, (float) $comm2->doctor_service_price);
        $this->assertEquals(12.00, (float) $comm2->commission_value);
    }

    public function test_contract_explicit_service_commissions_take_precedence()
    {
        $template = CommissionTemplate::create([
            'name' => 'قالب عمولات',
            'is_active' => true,
        ]);

        $template->items()->create([
            'service_id' => $this->service1->id,
            'doctor_service_price' => 50.00,
            'is_laser' => true,
            'commission_type' => CommissionTypeEnum::PERCENTAGE->value,
            'commission_value' => 10.00,
        ]);

        $contractService = app(ContractService::class);
        $request = $this->makeRequest(StoreStaffContractRequest::class, [
            'user_id' => $this->doctor->id,
            'title' => 'عقد مخصص',
            'contract_type' => ContractTypeEnum::DOCTOR->value,
            'is_active' => true,
            'commission_template_id' => $template->id,
            'service_commissions' => [
                [
                    'service_id' => $this->service3->id,
                    'doctor_service_price' => 300.00,
                    'is_laser' => false,
                    'commission_type' => CommissionTypeEnum::FIXED->value,
                    'commission_value' => 75.00,
                ],
            ],
        ]);

        $response = $contractService->store($request);
        $data = json_decode($response->getContent(), true)['data'];

        $contract = StaffContract::with('serviceCommissions')->find($data['id']);
        $this->assertCount(1, $contract->serviceCommissions);
        $this->assertEquals($this->service3->id, $contract->serviceCommissions->first()->service_id);
    }

    public function test_updating_contract_with_new_commission_template_clones_new_items()
    {
        $template1 = CommissionTemplate::create(['name' => 'Template 1', 'is_active' => true]);
        $template1->items()->create([
            'service_id' => $this->service1->id,
            'commission_type' => CommissionTypeEnum::PERCENTAGE->value,
            'commission_value' => 10.00,
        ]);

        $template2 = CommissionTemplate::create(['name' => 'Template 2', 'is_active' => true]);
        $template2->items()->create([
            'service_id' => $this->service2->id,
            'commission_type' => CommissionTypeEnum::PERCENTAGE->value,
            'commission_value' => 25.00,
        ]);

        $contract = StaffContract::create([
            'user_id' => $this->doctor->id,
            'title' => 'Original Contract',
            'contract_type' => ContractTypeEnum::DOCTOR->value,
            'is_active' => true,
            'commission_template_id' => $template1->id,
        ]);
        $contract->serviceCommissions()->create([
            'service_id' => $this->service1->id,
            'commission_type' => CommissionTypeEnum::PERCENTAGE->value,
            'commission_value' => 10.00,
        ]);

        $contractService = app(ContractService::class);
        $request = $this->makeRequest(UpdateStaffContractRequest::class, [
            'commission_template_id' => $template2->id,
        ], 'PUT');

        $contractService->update($contract->id, $request);

        $contract->refresh();
        $this->assertEquals($template2->id, $contract->commission_template_id);
        $this->assertCount(1, $contract->serviceCommissions);
        $this->assertEquals($this->service2->id, $contract->serviceCommissions->first()->service_id);
        $this->assertEquals(25.00, (float) $contract->serviceCommissions->first()->commission_value);
    }
}
