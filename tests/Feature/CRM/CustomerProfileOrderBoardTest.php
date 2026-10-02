<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Services\CRM\CustomerProfileOrderBoardService;
use App\Support\SchemaCache;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Khối "Hồ sơ đơn hàng" trong trang hồ sơ khách hàng.
 *
 * Logic này từng là 141 dòng `@php` trong `ego_order_documents/profile_box.blade.php`
 * — 7 câu truy vấn chạy thẳng trong view, không cách nào test. Chuyển sang service
 * rồi thì chốt lại đúng phần dễ sai nhất: cách nối từ hồ sơ ra đơn hàng.
 *
 * ## Điểm dễ hiểu nhầm
 * `crm_orders` KHÔNG có cột `customer_id`. Đường duy nhất tìm ra đơn là:
 * hồ sơ -> khách (customer_id / số điện thoại / tên đại lý) -> lead -> đơn theo
 * lead_id. Ai thêm nhánh tìm theo customer_id mà quên kiểm cột thì sẽ ra 0 đơn.
 */
final class CustomerProfileOrderBoardTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function tim_ra_don_qua_duong_khach_roi_lead(): void
    {
        $du = $this->duLieuMau();

        $data = $this->service()->viewData($du['profile']);

        $this->assertCount(2, $data['egoOrders']);
        $this->assertSame(
            ['DH-002', 'DH-001'],
            $data['egoOrders']->pluck('order_code')->all(),
            'Đơn phải sắp xếp mới trước (orderByDesc id).'
        );
    }

    /**
     * Gộp khách theo TÊN đại lý.
     *
     * Nhánh gộp theo số điện thoại trong mã cũ thực ra không bao giờ trả về quá
     * một bản ghi: `crm_customers.phone` có ràng buộc UNIQUE (phát hiện khi viết
     * test này — chèn bản ghi thứ hai cùng số thì CSDL từ chối). Đường mở rộng
     * thật sự là theo `name`, cột đó không unique.
     */
    #[Test]
    public function gom_khach_theo_ten_dai_ly_va_lay_ca_don_cua_ban_ghi_kia(): void
    {
        $du = $this->duLieuMau();

        $khachHai = DB::table('crm_customers')->insertGetId([
            'name' => 'Đại lý Phương Nam',   // trùng tên, khác số điện thoại
            'phone' => '0988777666',
            'customer_status' => 'lead',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $leadHai = DB::table('crm_leads')->insertGetId([
            'customer_id' => $khachHai,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('crm_orders')->insert([
            'lead_id' => $leadHai,
            'order_code' => 'DH-003',
            'total_amount' => 10000000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = $this->service()->viewData($du['profile']);

        $this->assertCount(3, $data['egoOrders'], 'Phải gộp cả đơn của bản ghi khách trùng tên.');
        $this->assertContains('DH-003', $data['egoOrders']->pluck('order_code')->all());
        $this->assertSame(175000000.0, $data['egoTotalRevenue']);
    }

    #[Test]
    public function cong_doanh_thu_va_da_thu(): void
    {
        $du = $this->duLieuMau();

        $data = $this->service()->viewData($du['profile']);

        $this->assertSame(165000000.0, $data['egoTotalRevenue']);

        if (SchemaCache::hasTable('crm_order_payments')) {
            $this->assertSame(50000000.0, $data['egoTotalPaid']);
        }
    }

    #[Test]
    public function nhom_tai_lieu_theo_don(): void
    {
        $du = $this->duLieuMau();

        $data = $this->service()->viewData($du['profile']);

        $this->assertCount(3, $data['egoOrderDocs']);
        $this->assertCount(2, $data['egoDocsByOrder'][$du['donIds'][0]]);
        $this->assertCount(1, $data['egoDocsByOrder'][$du['donIds'][1]]);
        $this->assertNotSame('', $data['egoFirstPreviewUrl']);
    }

    #[Test]
    public function ho_so_rong_thi_tra_ve_rong_chu_khong_vo(): void
    {
        $data = $this->service()->viewData(null);

        $this->assertCount(0, $data['egoOrders']);
        $this->assertCount(0, $data['egoOrderDocs']);
        $this->assertSame(0.0, $data['egoTotalRevenue']);
        $this->assertSame(0.0, $data['egoTotalPaid']);
        $this->assertSame('', $data['egoFirstPreviewUrl']);
        $this->assertSame('', $data['egoFirstName']);
    }

    #[Test]
    public function view_khong_con_tu_truy_van(): void
    {
        $nguon = (string) file_get_contents(
            resource_path('views/ego_order_documents/profile_box.blade.php')
        );

        $this->assertStringNotContainsString('DB::table', $nguon);
        $this->assertStringNotContainsString('SchemaCache::', $nguon);
    }

    /** @return array{profile: object, donIds: list<int>} */
    private function duLieuMau(): array
    {
        $khachId = DB::table('crm_customers')->insertGetId([
            'name' => 'Đại lý Phương Nam',
            'phone' => '0911222333',
            'customer_status' => 'member',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $leadId = DB::table('crm_leads')->insertGetId([
            'customer_id' => $khachId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $donIds = [];

        foreach ([['DH-001', 120000000], ['DH-002', 45000000]] as [$ma, $tien]) {
            $donIds[] = DB::table('crm_orders')->insertGetId([
                'lead_id' => $leadId,
                'order_code' => $ma,
                'total_amount' => $tien,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $taiLieu = [
            [$donIds[0], 'purchase_contract', 'hop-dong.pdf'],
            [$donIds[0], 'invoice', 'hoa-don.pdf'],
            [$donIds[1], 'quotation', 'bao-gia.pdf'],
        ];

        foreach ($taiLieu as [$don, $loai, $ten]) {
            DB::table('crm_order_documents')->insert([
                'order_id' => $don,
                'customer_id' => $khachId,
                'document_type' => $loai,
                'original_name' => $ten,
                'file_path' => 'documents/'.$ten,
                'size_bytes' => 12345,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (SchemaCache::hasTable('crm_order_payments')) {
            DB::table('crm_order_payments')->insert([
                'order_id' => $donIds[0],
                'amount' => 50000000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'profile' => (object) [
                'id' => 777,
                'customer_id' => $khachId,
                'phone' => '0911222333',
                'agent_name' => 'Đại lý Phương Nam',
            ],
            'donIds' => $donIds,
        ];
    }

    private function service(): CustomerProfileOrderBoardService
    {
        return app(CustomerProfileOrderBoardService::class);
    }
}
