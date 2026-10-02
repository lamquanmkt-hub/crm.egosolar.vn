<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Dự án dùng pnpm. Hai lockfile song song là nguồn lỗi kinh điển: máy dev cài
 * theo lock này, CI cài theo lock kia, build ra asset khác nhau — mà asset lại
 * được commit kèm repo (server không có Node) nên sai lệch đi thẳng ra production.
 */
final class PackageManagerTest extends TestCase
{
    #[Test]
    public function chi_co_mot_lockfile_va_do_la_pnpm(): void
    {
        $root = base_path();

        $this->assertFileExists($root.'/pnpm-lock.yaml', 'Thiếu pnpm-lock.yaml.');

        foreach (['package-lock.json', 'yarn.lock', 'bun.lockb'] as $la) {
            $this->assertFileDoesNotExist(
                $root.'/'.$la,
                "Còn $la — dự án đã chuyển sang pnpm, xoá lockfile này đi."
            );
        }
    }

    #[Test]
    public function package_json_ghim_dung_trinh_quan_ly_goi(): void
    {
        $pkg = json_decode((string) file_get_contents(base_path('package.json')), true);

        $this->assertIsArray($pkg);
        $this->assertArrayHasKey('packageManager', $pkg, 'package.json thiếu "packageManager".');
        $this->assertStringStartsWith('pnpm@', (string) $pkg['packageManager']);
    }

    #[Test]
    public function ci_va_script_deploy_khong_con_goi_npm(): void
    {
        $files = [
            '.github/workflows/ci.yml',
            'scripts/deploy-assets.sh',
        ];

        foreach ($files as $rel) {
            $noiDung = (string) file_get_contents(base_path($rel));

            $this->assertDoesNotMatchRegularExpression(
                '/\bnpm (ci|install|run)\b/',
                $noiDung,
                "$rel vẫn gọi npm — phải dùng pnpm để khớp với pnpm-lock.yaml."
            );
        }
    }
}
