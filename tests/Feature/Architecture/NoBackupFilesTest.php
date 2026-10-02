<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Repo không chứa bản sao lưu thủ công.
 *
 * ## Vì sao
 * Đợt production 2026-07-23 để lại 21 tệp backup lẫn trong repo (1,4 MB, dọn ở
 * commit c1994bc). Đến 2026-09-05 vẫn còn sót 3 tệp nữa. Tác hại không chỉ là
 * rác:
 *
 * - `PaymentRequestController.php.save.1` khai `class PaymentRequestController`
 *   trong `App\Http\Controllers` — trùng tên với bản thật ở `Finance\`. Đọc
 *   nhầm bản cũ là sửa nhầm chỗ. (Composer KHÔNG nạp vì đuôi không phải `.php`,
 *   nên nó chỉ đánh lừa người đọc chứ không đổi hành vi.)
 * - Rà "còn chỗ nào chưa sửa" bị nhiễu: bản sao lưu vẫn chứa mã cũ nên tưởng
 *   như quét chưa xong.
 *
 * Git đã giữ lịch sử; cần bản cũ thì `git show <commit>:<đường dẫn>`.
 *
 * ## Cách bỏ qua có chủ đích
 * Nếu thật sự cần giữ một tệp có tên trông như backup, khai vào {@see ALLOWED}
 * kèm lý do — đừng nới lỏng mẫu tìm.
 */
final class NoBackupFilesTest extends TestCase
{
    /**
     * Mẫu tên gợi ý bản sao lưu thủ công.
     *
     * Cố ý KHÔNG bắt `*.example`, `*.dist`, `*.stub` — đó là tệp mẫu hợp lệ.
     */
    private const PATTERNS = [
        '/\.bak$/i',
        '/\.bak[-.]/i',
        '/\.backup($|[-.])/i',
        '/\.save(\.\d+)?$/i',
        '/\.old$/i',
        '/\.orig$/i',
        '/~$/',
        '/\.disabled_\d/i',
        '/_old_file$/i',
        '/\.php\.\d+$/i',
        '/\.blade\.php\.[a-z0-9]/i',
    ];

    /** @var array<string, string> đường dẫn => lý do giữ */
    private const ALLOWED = [];

    public function test_repo_khong_con_tep_sao_luu_thu_cong(): void
    {
        $process = new Process(['git', 'ls-files'], base_path());
        $process->run();

        $this->assertTrue($process->isSuccessful(), 'không chạy được `git ls-files`');

        $offenders = [];

        foreach (preg_split('/\R/', trim($process->getOutput())) ?: [] as $path) {
            if ($path === '' || isset(self::ALLOWED[$path])) {
                continue;
            }

            foreach (self::PATTERNS as $pattern) {
                if (preg_match($pattern, $path)) {
                    $offenders[] = $path;
                    break;
                }
            }
        }

        $this->assertSame([], $offenders,
            "Có tệp sao lưu thủ công trong repo:\n".implode("\n", $offenders)."\n\n".
            "Xoá đi — git đã giữ lịch sử (`git show <commit>:<đường dẫn>` lấy lại được).\n".
            'Nếu thật sự cần giữ, khai vào hằng ALLOWED trong '.self::class.' kèm lý do.');
    }

    /**
     * Bản chụp cấu trúc CSDL cũng không được chứa bảng sao lưu thủ công.
     *
     * `database/schema/mysql-schema.sql` là thứ Laravel nạp khi dựng CSDL rỗng.
     * Ngày 2026-09-05 nó còn khai đủ 25 bảng backup đã xoá khỏi production —
     * nghĩa là mỗi lần dựng DB test mới sẽ tạo lại đúng đống rác vừa dọn.
     *
     * ⚠️ Tệp này nằm trong `.gitignore` (`/database/schema`) nên KHÔNG có trong
     * repo và KHÔNG có trên CI — ở đó ca này luôn bị bỏ qua. Nó chỉ có tác dụng
     * trên máy dev, nơi bản chụp thật sự tồn tại. Ghi rõ ra đây để không ai
     * tưởng CI đang canh giúp.
     */
    public function test_ban_chup_cau_truc_khong_khai_bang_sao_luu(): void
    {
        $path = database_path('schema/mysql-schema.sql');

        if (! is_file($path)) {
            $this->markTestSkipped('chưa có bản chụp cấu trúc');
        }

        $sql = (string) file_get_contents($path);

        preg_match_all('/^(?:CREATE TABLE|DROP TABLE IF EXISTS) `([^`]+)`/m', $sql, $m);

        $offenders = array_values(array_unique(array_filter(
            $m[1],
            static fn (string $table): bool => (bool) preg_match(
                '/^backup_|_backup(_|$)|^ego_bak_|_bk_|_before_merge$/i', $table),
        )));

        $this->assertSame([], $offenders,
            "Bản chụp cấu trúc còn khai bảng sao lưu thủ công:\n".implode("\n", $offenders)."\n\n".
            'Dựng CSDL mới từ bản này sẽ tạo lại đúng đống rác đó.');
    }
}
