<?php
namespace Tests\Feature;

use App\Models\{Staff, StaffPayment, StaffWorkLog};
use App\Services\{StaffPayroll, StaffPayPeriod};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class StaffPayrollTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','database.connections.sqlite.url'=>null]);
        DB::purge('sqlite');
        Schema::create('staff', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('salary_type'); $t->string('payment_period')->default('Monthly');
            $t->decimal('per_suit_rate',10,2); $t->decimal('monthly_salary',10,2)->default(0); $t->timestamps();
        });
        Schema::create('staff_work_logs', function (Blueprint $t) {
            $t->id(); $t->integer('staff_id'); $t->integer('order_id')->nullable()->unique(); $t->string('garment')->nullable(); $t->text('notes')->nullable(); $t->decimal('quantity',8,2); $t->decimal('rate',10,2);
            $t->decimal('amount',10,2); $t->date('completed_on'); $t->timestamps();
        });
        Schema::create('staff_advances', function (Blueprint $t) {
            $t->id(); $t->integer('staff_id'); $t->decimal('amount',10,2); $t->string('method');
            $t->date('given_on'); $t->string('status')->default('Active'); $t->text('notes')->nullable(); $t->integer('recorded_by')->nullable();
            $t->timestamp('reversed_at')->nullable(); $t->integer('reversed_by')->nullable(); $t->timestamps();
        });
        Schema::create('staff_payments', function (Blueprint $t) {
            $t->id(); $t->integer('staff_id'); $t->decimal('amount',10,2); $t->string('method'); $t->string('period')->nullable();
            $t->date('paid_on'); $t->string('status'); $t->text('notes')->nullable(); $t->integer('recorded_by')->nullable();
            $t->string('operation_key')->unique(); $t->timestamp('reversed_at')->nullable();
            $t->integer('reverses_payment_id')->nullable(); $t->json('earnings_snapshot')->nullable(); $t->timestamps();
        });
    }

    private function staff(): Staff
    {
        $staff = Staff::create(['name'=>'Tailor','salary_type'=>'Per Suit','per_suit_rate'=>125.35,'payment_period'=>'Weekly']);
        foreach (['2026-09-07'=>3,'2026-09-13'=>2,'2026-09-14'=>4] as $date=>$qty) {
            StaffWorkLog::create(['staff_id'=>$staff->id,'quantity'=>$qty,'rate'=>125.35,'amount'=>round($qty*125.35,2),'completed_on'=>$date]);
        }
        return $staff;
    }

    private function pay(Staff $s, float $amount, string $key, ?string $period='2026-W37'): StaffPayment
    {
        return app(StaffPayroll::class)->pay($s,['amount'=>$amount,'method'=>'Cash','operation_key'=>$key],$period);
    }

    public function test_partial_and_final_payments_snapshot_earnings_and_keep_historical_rates(): void
    {
        $s=$this->staff();
        $this->assertSame(9.0,$s->dueFor()['pieces']);
        $this->assertEquals(1128.15,$s->dueFor()['earned']);
        $first=$this->pay($s,200.15,'first',null);
        $this->assertEquals(928.0,$first->earnings_snapshot['remaining']);
        $s->update(['per_suit_rate'=>900]);
        $last=$this->pay($s,928.0,'last',null);
        $this->assertSame('Paid',$last->status);
        $this->assertEquals(1128.15,$s->dueFor()['paid']);
        $this->assertEquals(928.0,$first->fresh()->earnings_snapshot['remaining']);
    }

    public function test_overpayment_is_rejected_without_writing(): void
    {
        $s=$this->staff();
        try { $this->pay($s,1128.16,'too-much',null); $this->fail('Accepted overpayment'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('amount',$e->errors()); }
        $this->assertSame(0,StaffPayment::count());
    }

    public function test_duplicate_submission_and_overlapping_cycles_cannot_pay_twice(): void
    {
        $s=$this->staff();
        $s->update(['salary_type'=>'Both', 'monthly_salary'=>1000]);
        $this->pay($s,100,'same');
        try { $this->pay($s,100,'same'); $this->fail('Accepted duplicate'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(409,$e->getStatusCode()); }
        try { $this->pay($s,100,'overlap','2026-09'); $this->fail('Accepted overlap'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('period',$e->errors()); }
        $this->assertSame(1,StaffPayment::count());
    }

    public function test_reversals_are_in_history_but_not_in_paid_totals(): void
    {
        $s=$this->staff(); $p=$this->pay($s,100,'original',null); $p->update(['reversed_at'=>now()]);
        StaffPayment::create(['staff_id'=>$s->id,'amount'=>100,'method'=>'Cash','period'=>null,
            'paid_on'=>'2026-09-11','status'=>'Reversal','reverses_payment_id'=>$p->id,'operation_key'=>'reverse']);
        $this->assertEquals(0,$s->dueFor()['paid']);
        $this->assertSame(2,$s->paymentHistory()->count());
    }

    public function test_calendar_boundaries_and_retainer_rounding(): void
    {
        $s=$this->staff();
        $s->update(['salary_type'=>'Both', 'monthly_salary'=>0]);
        $this->assertEquals(376.05,$s->dueFor('2026-09-07')['earned']);
        $this->assertEquals(1128.15,$s->dueFor('2026-09')['earned']);
        [$a,$b]=StaffPayPeriod::bounds('2026-W01');
        $this->assertSame('2025-12-29',$a->toDateString());
        $this->assertSame('2026-01-04',$b->toDateString());
        $s->update(['salary_type'=>'Monthly','monthly_salary'=>100]);
        $total=0;
        for ($day=1;$day<=31;$day++) $total+=$s->dueFor(sprintf('2026-01-%02d',$day))['earned'];
        $this->assertEqualsWithDelta(100,$total,0.001);
        foreach (['2026-02-30','2026-13','2025-W53','bad'] as $bad) {
            try { StaffPayPeriod::bounds($bad); $this->fail('Accepted invalid period'); }
            catch (ValidationException $e) { $this->assertArrayHasKey('period',$e->errors()); }
        }
    }
}
