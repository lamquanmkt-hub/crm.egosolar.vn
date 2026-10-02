@extends('layouts.app')

@section('content')
<?php
    $departmentMeta = $departments[$department] ?? ['label' => $department, 'short' => $department, 'icon' => 'bi-folder'];
    $scopeMeta = [
        'all' => ['label' => 'Tất cả tài liệu', 'icon' => 'bi-files'],
        'recent' => ['label' => 'Gần đây', 'icon' => 'bi-clock-history'],
        'approval' => ['label' => 'Tài liệu cần duyệt', 'icon' => 'bi-patch-check'],
        'trash' => ['label' => 'Thùng rác', 'icon' => 'bi-trash3'],
    ];
    $statusClasses = [
        'not_required' => 'neutral',
        'draft' => 'neutral',
        'pending' => 'pending',
        'revision' => 'revision',
        'approved' => 'approved',
        'archived' => 'archived',
    ];
    $formatBytes = function ($bytes) {
        $bytes = (int) $bytes;
        if ($bytes <= 0) return '0 B';
        $units = ['B','KB','MB','GB','TB'];
        $i = min((int) floor(log($bytes, 1024)), count($units)-1);
        return number_format($bytes / (1024 ** $i), $i === 0 ? 0 : 1, ',', '.').' '.$units[$i];
    };
    $fileIcon = function ($file) {
        $ext = strtolower(pathinfo($file->original_name ?: $file->path, PATHINFO_EXTENSION));
        return match (true) {
            $ext === 'pdf' => ['bi-file-earmark-pdf-fill','pdf'],
            in_array($ext, ['doc','docx','odt'], true) => ['bi-file-earmark-word-fill','word'],
            in_array($ext, ['xls','xlsx','ods','csv'], true) => ['bi-file-earmark-excel-fill','excel'],
            in_array($ext, ['ppt','pptx'], true) => ['bi-file-earmark-slides-fill','slides'],
            in_array($ext, ['jpg','jpeg','png','webp','gif','bmp'], true) => ['bi-file-earmark-image-fill','image'],
            in_array($ext, ['zip','rar','7z','tar','gz'], true) => ['bi-file-earmark-zip-fill','archive'],
            default => ['bi-file-earmark-fill','file'],
        };
    };
?>

<style>
:root{
    --edw-navy:#102a43;
    --edw-blue:#1d4ed8;
    --edw-teal:#0f9f95;
    --edw-bg:#f4f7fb;
    --edw-line:#e3eaf3;
    --edw-muted:#64748b;
    --edw-white:#fff;
}
.edw-page{min-height:calc(100vh - 72px);background:var(--edw-bg);padding:22px;color:#0f172a;font-family:'Be Vietnam Pro',system-ui,-apple-system,sans-serif}
.edw-shell{max-width:1540px;margin:0 auto}
.edw-header{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:4px 2px 18px}
.edw-eyebrow{font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#0f9f95;font-weight:900;margin-bottom:5px}
.edw-header h1{margin:0;font-size:28px;line-height:1.1;font-weight:900;letter-spacing:-.035em;color:var(--edw-navy)}
.edw-header p{margin:7px 0 0;color:var(--edw-muted);font-size:13px}
.edw-head-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.edw-btn{border:0;border-radius:12px;height:42px;padding:0 16px;display:inline-flex;align-items:center;justify-content:center;gap:8px;font-weight:800;font-size:13px;text-decoration:none!important;cursor:pointer;transition:.15s ease;white-space:nowrap}
.edw-btn:hover{transform:translateY(-1px)}
.edw-btn-primary{background:linear-gradient(135deg,#0f9f95,#087d78);color:#fff;box-shadow:0 10px 22px rgba(15,159,149,.18)}
.edw-btn-light{background:#fff;color:#334155;border:1px solid var(--edw-line)}
.edw-btn-danger{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}
.edw-btn-sm{height:34px;padding:0 11px;border-radius:10px;font-size:12px}
.edw-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:15px}
.edw-kpi{background:#fff;border:1px solid var(--edw-line);border-radius:16px;padding:15px 16px;display:flex;align-items:center;gap:13px;box-shadow:0 8px 22px rgba(15,23,42,.035)}
.edw-kpi-icon{width:42px;height:42px;border-radius:13px;display:flex;align-items:center;justify-content:center;background:#eff6ff;color:#2563eb;font-size:18px}
.edw-kpi:nth-child(2) .edw-kpi-icon{background:#f5f3ff;color:#7c3aed}
.edw-kpi:nth-child(3) .edw-kpi-icon{background:#fff7ed;color:#ea580c}
.edw-kpi:nth-child(4) .edw-kpi-icon{background:#ecfdf5;color:#059669}
.edw-kpi:nth-child(5) .edw-kpi-icon{background:#f8fafc;color:#475569}
.edw-kpi b{display:block;font-size:21px;line-height:1;font-weight:900;color:var(--edw-navy)}
.edw-kpi span{display:block;margin-top:5px;font-size:11.5px;color:var(--edw-muted);font-weight:700}
.edw-workspace{display:grid;grid-template-columns:245px minmax(0,1fr);gap:15px;align-items:start}
.edw-rail,.edw-main-card{background:#fff;border:1px solid var(--edw-line);border-radius:18px;box-shadow:0 10px 28px rgba(15,23,42,.04)}
.edw-rail{padding:14px;position:sticky;top:84px}
.edw-rail-title{font-size:10.5px;letter-spacing:.11em;text-transform:uppercase;color:#94a3b8;font-weight:900;margin:8px 8px 9px}
.edw-nav{display:flex;flex-direction:column;gap:4px}
.edw-nav a{display:flex;align-items:center;gap:10px;min-height:40px;padding:8px 10px;border-radius:11px;text-decoration:none;color:#475569;font-size:12.5px;font-weight:750}
.edw-nav a:hover{background:#f8fafc;color:#0f172a}
.edw-nav a.active{background:#e9f9f7;color:#087d78;font-weight:900}
.edw-nav i{width:18px;text-align:center;font-size:15px}
.edw-nav .count{margin-left:auto;min-width:25px;height:22px;padding:0 7px;border-radius:999px;background:#edf2f7;color:#64748b;display:flex;align-items:center;justify-content:center;font-size:10.5px;font-weight:900}
.edw-nav a.active .count{background:#bff1eb;color:#087d78}
.edw-divider{height:1px;background:#edf2f7;margin:14px 0}
.edw-main-card{overflow:hidden}
.edw-toolbar{padding:16px;border-bottom:1px solid var(--edw-line)}
.edw-breadcrumb{display:flex;align-items:center;gap:7px;flex-wrap:wrap;font-size:12px;color:#64748b;margin-bottom:13px}
.edw-breadcrumb a{color:#2563eb;text-decoration:none;font-weight:800}
.edw-toolbar-row{display:grid;grid-template-columns:minmax(250px,1.4fr) repeat(2,minmax(145px,.55fr)) auto;gap:9px;align-items:center}
.edw-search{position:relative}
.edw-search i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#94a3b8}
.edw-input,.edw-select{width:100%;height:42px;border:1px solid #dce5ef;border-radius:12px;background:#fff;color:#0f172a;font-size:12.5px;padding:0 13px;outline:none}
.edw-search .edw-input{padding-left:38px}
.edw-input:focus,.edw-select:focus{border-color:#5eead4;box-shadow:0 0 0 3px rgba(45,212,191,.12)}
.edw-toolbar-actions{display:flex;gap:8px}
.edw-view-toggle{display:flex;border:1px solid var(--edw-line);border-radius:12px;overflow:hidden;background:#fff}
.edw-view-toggle button{width:40px;height:40px;border:0;background:#fff;color:#64748b;cursor:pointer}
.edw-view-toggle button.active{background:#e9f9f7;color:#087d78}
.edw-clipboard{margin:0 16px 12px;padding:12px 14px;background:#fffbeb;border:1px solid #fde68a;border-radius:13px;display:flex;align-items:center;justify-content:space-between;gap:12px;font-size:12px;color:#854d0e}
.edw-list-head{padding:14px 17px;border-bottom:1px solid var(--edw-line);display:flex;justify-content:space-between;align-items:center;gap:14px}
.edw-list-head h2{margin:0;font-size:17px;font-weight:900;color:var(--edw-navy)}
.edw-list-head p{margin:4px 0 0;font-size:11.5px;color:var(--edw-muted)}
.edw-table-wrap{overflow:auto}
.edw-table{width:100%;border-collapse:collapse;min-width:970px}
.edw-table th{padding:11px 14px;text-align:left;background:#f8fafc;color:#64748b;font-size:10.5px;letter-spacing:.06em;text-transform:uppercase;font-weight:900;border-bottom:1px solid var(--edw-line)}
.edw-table td{padding:13px 14px;border-bottom:1px solid #eef2f7;vertical-align:middle;font-size:12px}
.edw-table tr:hover td{background:#fbfdff}
.edw-name-cell{display:flex;align-items:center;gap:12px;min-width:310px}
.edw-file-icon{width:42px;height:42px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:19px;flex:0 0 auto}
.edw-file-icon.folder{background:#fff7ed;color:#f97316}
.edw-file-icon.pdf{background:#fff1f2;color:#e11d48}
.edw-file-icon.word{background:#eff6ff;color:#2563eb}
.edw-file-icon.excel{background:#ecfdf5;color:#059669}
.edw-file-icon.slides{background:#fff7ed;color:#ea580c}
.edw-file-icon.image{background:#f5f3ff;color:#7c3aed}
.edw-file-icon.archive{background:#f8fafc;color:#475569}
.edw-file-icon.file{background:#eef2ff;color:#4f46e5}
.edw-item-title{font-size:12.8px;font-weight:900;color:#0f172a;line-height:1.35;max-width:450px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.edw-item-sub{margin-top:4px;color:#64748b;font-size:10.8px;display:flex;gap:9px;align-items:center;flex-wrap:wrap}
.edw-item-sub a{color:#2563eb;text-decoration:none;font-weight:800}
.edw-badge{display:inline-flex;align-items:center;gap:5px;height:25px;padding:0 9px;border-radius:999px;font-size:10.5px;font-weight:900;white-space:nowrap}
.edw-badge.neutral{background:#f1f5f9;color:#475569}
.edw-badge.pending{background:#f3e8ff;color:#7e22ce}
.edw-badge.revision{background:#fff7ed;color:#c2410c}
.edw-badge.approved{background:#dcfce7;color:#15803d}
.edw-badge.archived{background:#e2e8f0;color:#475569}
.edw-actions{display:flex;justify-content:flex-end;gap:7px;align-items:center}
.edw-icon-btn{width:34px;height:34px;border:1px solid var(--edw-line);border-radius:10px;background:#fff;color:#475569;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;text-decoration:none}
.edw-icon-btn:hover{border-color:#99f6e4;color:#087d78;background:#f0fdfa}
.edw-menu{position:relative}
.edw-menu summary{list-style:none}.edw-menu summary::-webkit-details-marker{display:none}
.edw-menu-panel{position:absolute;right:0;top:40px;z-index:20;width:190px;background:#fff;border:1px solid var(--edw-line);border-radius:13px;box-shadow:0 18px 45px rgba(15,23,42,.15);padding:6px}
.edw-menu-panel a,.edw-menu-panel button{width:100%;border:0;background:#fff;display:flex;align-items:center;gap:9px;padding:9px 10px;border-radius:9px;color:#334155;font-size:11.5px;font-weight:750;text-align:left;text-decoration:none;cursor:pointer}
.edw-menu-panel a:hover,.edw-menu-panel button:hover{background:#f8fafc;color:#0f172a}
.edw-menu-panel .danger{color:#be123c}
.edw-menu-panel form{margin:0}
.edw-empty{padding:65px 20px;text-align:center;color:#64748b}
.edw-empty-icon{width:66px;height:66px;border-radius:20px;background:#ecfdf5;color:#0f9f95;display:flex;align-items:center;justify-content:center;margin:0 auto 13px;font-size:28px}
.edw-empty h3{margin:0 0 5px;color:#0f172a;font-size:17px;font-weight:900}
.edw-grid-mode .edw-table thead{display:none}
.edw-grid-mode .edw-table,.edw-grid-mode .edw-table tbody{display:block;min-width:0}
.edw-grid-mode .edw-table tbody{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;padding:14px}
.edw-grid-mode .edw-table tr{display:grid;grid-template-columns:1fr auto;background:#fff;border:1px solid var(--edw-line);border-radius:15px;overflow:visible;min-width:0}
.edw-grid-mode .edw-table td{display:none;border:0;padding:0}
.edw-grid-mode .edw-table td:first-child{display:block;padding:16px;min-width:0}
.edw-grid-mode .edw-table td:last-child{display:flex;padding:12px;align-items:flex-end}
.edw-grid-mode .edw-name-cell{min-width:0;align-items:flex-start}
.edw-grid-mode .edw-item-title{white-space:normal;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.edw-pagination{padding:14px 17px;border-top:1px solid var(--edw-line)}
.edw-modal{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1050;display:none;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(3px)}
.edw-modal.show{display:flex}
.edw-modal-box{width:min(640px,100%);max-height:90vh;overflow:auto;background:#fff;border-radius:20px;box-shadow:0 28px 80px rgba(15,23,42,.25)}
.edw-modal-head{display:flex;align-items:center;justify-content:space-between;padding:18px 20px;border-bottom:1px solid var(--edw-line)}
.edw-modal-head h3{margin:0;font-size:18px;font-weight:900;color:var(--edw-navy)}
.edw-modal-body{padding:20px}
.edw-modal-foot{padding:14px 20px;border-top:1px solid var(--edw-line);display:flex;justify-content:flex-end;gap:9px}
.edw-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:13px}
.edw-field{display:flex;flex-direction:column;gap:6px}
.edw-field.full{grid-column:1/-1}
.edw-label{font-size:11.5px;font-weight:850;color:#334155}
.edw-textarea{min-height:90px;padding:11px 13px;resize:vertical}
.edw-dropzone{border:1.5px dashed #99dcd6;border-radius:16px;background:#f0fdfa;padding:28px;text-align:center;cursor:pointer}
.edw-dropzone i{font-size:28px;color:#0f9f95}
.edw-dropzone strong{display:block;margin-top:8px;color:#0f172a;font-size:13px}
.edw-dropzone span{display:block;margin-top:5px;color:#64748b;font-size:11px}
.edw-file-selection{margin-top:10px;font-size:11.5px;color:#475569}
.edw-drawer-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.62);z-index:1080;display:none;backdrop-filter:blur(4px)}
.edw-drawer-backdrop.show{display:block}
.edw-drawer{position:fixed;left:50%;top:50%;right:auto;width:min(94vw,1540px);height:min(92vh,980px);background:#fff;z-index:1090;border:1px solid rgba(255,255,255,.65);border-radius:20px;box-shadow:0 34px 100px rgba(15,23,42,.36);transform:translate(-50%,-48%) scale(.985);opacity:0;pointer-events:none;transition:transform .18s ease,opacity .18s ease;display:flex;flex-direction:column;overflow:hidden}
.edw-drawer.show{transform:translate(-50%,-50%) scale(1);opacity:1;pointer-events:auto}
.edw-drawer-head{min-height:64px;padding:11px 14px 11px 18px;border-bottom:1px solid var(--edw-line);display:flex;align-items:center;justify-content:space-between;gap:14px;background:#fff}
.edw-drawer-title-wrap{min-width:0;display:flex;align-items:center;gap:11px}
.edw-drawer-file-icon{width:36px;height:36px;border-radius:11px;background:#ecfdf5;color:#087565;display:flex;align-items:center;justify-content:center;flex:0 0 auto;font-size:17px}
.edw-drawer-head h3{margin:0;font-size:16px;font-weight:900;color:var(--edw-navy);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.edw-drawer-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex:0 0 auto}
.edw-drawer-body{overflow:hidden;flex:1;padding:0;background:#e9eef4;min-height:0}
.edw-preview{height:100%;border:0;border-radius:0;overflow:hidden;background:#e9eef4;margin:0}
.edw-preview iframe{width:100%;height:100%;border:0;display:block;background:#fff}
.edw-viewer-loading{height:100%;display:flex;align-items:center;justify-content:center;background:#f8fafc}
.edw-viewer-edit-panel{position:absolute;top:64px;right:0;bottom:0;width:min(420px,100%);padding:18px;background:#fff;border-left:1px solid var(--edw-line);box-shadow:-16px 0 40px rgba(15,23,42,.14);overflow:auto;transform:translateX(102%);transition:transform .2s ease;z-index:4}
.edw-viewer-edit-panel.show{transform:translateX(0)}
.edw-viewer-edit-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:15px}
.edw-viewer-edit-head h4{margin:0;color:var(--edw-navy);font-size:15px;font-weight:900}
@media(max-width:760px){.edw-drawer{width:100vw;height:100vh;max-width:none;max-height:none;border-radius:0}.edw-drawer-head{padding:9px 10px 9px 12px}.edw-drawer-actions .edw-btn span{display:none}.edw-drawer-actions .edw-btn{width:38px;padding:0}.edw-viewer-edit-panel{top:58px;width:100%}}
.edw-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-bottom:16px}
.edw-detail-box{padding:11px 12px;border:1px solid var(--edw-line);border-radius:12px;background:#fff}
.edw-detail-box span{display:block;font-size:10px;color:#94a3b8;text-transform:uppercase;font-weight:900;letter-spacing:.05em}
.edw-detail-box b{display:block;margin-top:5px;font-size:12px;color:#0f172a}
.edw-tabs{display:flex;gap:5px;border-bottom:1px solid var(--edw-line);margin-bottom:13px}
.edw-tabs button{border:0;background:none;padding:9px 10px;font-size:11.5px;font-weight:850;color:#64748b;cursor:pointer;border-bottom:2px solid transparent}
.edw-tabs button.active{color:#0f9f95;border-color:#0f9f95}
.edw-tab-pane{display:none}.edw-tab-pane.active{display:block}
.edw-timeline{display:flex;flex-direction:column;gap:12px}
.edw-timeline-item{position:relative;padding-left:20px}
.edw-timeline-item:before{content:'';position:absolute;left:2px;top:5px;width:8px;height:8px;border-radius:99px;background:#14b8a6}
.edw-timeline-item:after{content:'';position:absolute;left:5.5px;top:15px;bottom:-14px;width:1px;background:#dbe7ee}
.edw-timeline-item:last-child:after{display:none}
.edw-timeline-item strong{font-size:11.5px;color:#0f172a}
.edw-timeline-item p{margin:3px 0 0;color:#64748b;font-size:10.8px;line-height:1.5}
.edw-version{display:flex;justify-content:space-between;gap:12px;padding:11px;border:1px solid var(--edw-line);border-radius:12px;margin-bottom:8px}
.edw-version strong{font-size:12px}.edw-version small{display:block;margin-top:3px;color:#64748b}
.edw-alert{padding:12px 14px;border-radius:12px;margin-bottom:12px;font-size:12px;font-weight:700}
.edw-alert-success{background:#ecfdf5;color:#047857;border:1px solid #a7f3d0}
.edw-alert-error{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}
@media(max-width:1100px){.edw-kpis{grid-template-columns:repeat(3,1fr)}.edw-workspace{grid-template-columns:1fr}.edw-rail{position:static}.edw-rail .edw-nav{display:grid;grid-template-columns:repeat(4,1fr)}.edw-rail-title,.edw-divider{display:none}.edw-grid-mode .edw-table tbody{grid-template-columns:repeat(2,1fr)}}
@media(max-width:760px){.edw-page{padding:12px}.edw-header{align-items:flex-start}.edw-header h1{font-size:23px}.edw-kpis{grid-template-columns:1fr 1fr}.edw-kpi:nth-child(5){grid-column:1/-1}.edw-rail .edw-nav{display:flex;flex-direction:row;overflow:auto}.edw-nav a{white-space:nowrap}.edw-toolbar-row{grid-template-columns:1fr 1fr}.edw-search{grid-column:1/-1}.edw-toolbar-actions{grid-column:1/-1;justify-content:space-between}.edw-grid-mode .edw-table tbody{grid-template-columns:1fr}.edw-form-grid{grid-template-columns:1fr}.edw-field.full{grid-column:auto}.edw-detail-grid{grid-template-columns:1fr}.edw-head-actions .edw-btn-light{display:none}}
</style>

<div class="edw-page">
    <div class="edw-shell">
        <div class="edw-header">
            <div>
                <div class="edw-eyebrow">Không gian tài liệu nội bộ</div>
                <h1>Hồ sơ công ty</h1>
                <p>Quản lý tài liệu theo phòng ban, phiên bản, phê duyệt và lịch sử trong một workspace thống nhất.</p>
            </div>
            <?php if ($canManageDepartment && $scope !== 'trash'): ?>
                <div class="edw-head-actions">
                    <button class="edw-btn edw-btn-light" type="button" onclick="openEdwModal('folderModal')"><i class="bi bi-folder-plus"></i> Tạo thư mục</button>
                    <button class="edw-btn edw-btn-primary" type="button" onclick="openEdwModal('uploadModal')"><i class="bi bi-cloud-arrow-up"></i> Tải tài liệu</button>
                </div>
            <?php endif; ?>
        </div>

        <?php if (session('success')): ?>
            <div class="edw-alert edw-alert-success"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>
        <?php endif; ?>
        <?php if ($errors->any()): ?>
            <div class="edw-alert edw-alert-error"><strong>Chưa thể thực hiện:</strong> {{ $errors->first() }}</div>
        <?php endif; ?>

        <div class="edw-kpis">
            <div class="edw-kpi"><div class="edw-kpi-icon"><i class="bi bi-files"></i></div><div><b>{{ number_format($stats['files']) }}</b><span>Tổng tài liệu</span></div></div>
            <div class="edw-kpi"><div class="edw-kpi-icon"><i class="bi bi-folder2-open"></i></div><div><b>{{ number_format($stats['folders']) }}</b><span>Thư mục</span></div></div>
            <div class="edw-kpi"><div class="edw-kpi-icon"><i class="bi bi-patch-exclamation"></i></div><div><b>{{ number_format($stats['pending']) }}</b><span>Đang chờ duyệt</span></div></div>
            <div class="edw-kpi"><div class="edw-kpi-icon"><i class="bi bi-calendar2-check"></i></div><div><b>{{ number_format($stats['review_due']) }}</b><span>Cần rà soát 30 ngày</span></div></div>
            <div class="edw-kpi"><div class="edw-kpi-icon"><i class="bi bi-hdd"></i></div><div><b>{{ $formatBytes($stats['storage']) }}</b><span>Dung lượng đang dùng</span></div></div>
        </div>

        <div class="edw-workspace">
            <aside class="edw-rail">
                <div class="edw-rail-title">Phạm vi</div>
                <nav class="edw-nav">
                    <?php foreach ($scopeMeta as $scopeKey => $meta): ?>
                        <a class="{{ $scope === $scopeKey ? 'active' : '' }}" href="{{ route('company-documents.index', ['department' => $department, 'scope' => $scopeKey]) }}">
                            <i class="bi {{ $meta['icon'] }}"></i><span>{{ $meta['label'] }}</span><span class="count">{{ $scopeCounts[$scopeKey] ?? 0 }}</span>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <div class="edw-divider"></div>
                <div class="edw-rail-title">Phòng ban</div>
                <nav class="edw-nav">
                    <?php foreach ($allowed as $depKey): ?>
                        <?php $dep = $departments[$depKey] ?? ['label' => $depKey, 'icon' => 'bi-folder']; ?>
                        <a class="{{ $department === $depKey ? 'active' : '' }}" href="{{ route('company-documents.index', ['department' => $depKey, 'scope' => 'all']) }}">
                            <i class="bi {{ $dep['icon'] ?? 'bi-folder' }}"></i><span>{{ $dep['label'] }}</span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </aside>

            <main class="edw-main-card" id="edwContentCard">
                <div class="edw-toolbar">
                    <div class="edw-breadcrumb">
                        <a href="{{ route('company-documents.index', ['department' => $department]) }}"><i class="bi {{ $departmentMeta['icon'] ?? 'bi-folder' }}"></i> {{ $departmentMeta['label'] }}</a>
                        <?php foreach ($breadcrumbs as $crumb): ?>
                            <i class="bi bi-chevron-right"></i>
                            <a href="{{ route('company-documents.index', ['department' => $department, 'folder' => $crumb->id]) }}">{{ $crumb->name }}</a>
                        <?php endforeach; ?>
                        <?php if ($scope !== 'all'): ?>
                            <i class="bi bi-chevron-right"></i><strong>{{ $scopeMeta[$scope]['label'] ?? $scope }}</strong>
                        <?php endif; ?>
                    </div>

                    <form method="get" action="{{ route('company-documents.index') }}" class="edw-toolbar-row">
                        <input type="hidden" name="department" value="{{ $department }}">
                        <input type="hidden" name="scope" value="{{ $scope }}">
                        <?php if ($currentFolder): ?><input type="hidden" name="folder" value="{{ $currentFolder->id }}"><?php endif; ?>
                        <div class="edw-search"><i class="bi bi-search"></i><input class="edw-input" name="q" value="{{ request('q') }}" placeholder="Tìm tên file, mô tả hoặc từ khóa..."></div>
                        <select class="edw-select" name="type" onchange="this.form.submit()">
                            <option value="">Tất cả định dạng</option>
                            <option value="pdf" @selected(request('type') === 'pdf')>PDF</option>
                            <option value="office" @selected(request('type') === 'office')>Word / Excel / PowerPoint</option>
                            <option value="image" @selected(request('type') === 'image')>Hình ảnh</option>
                            <option value="archive" @selected(request('type') === 'archive')>File nén</option>
                        </select>
                        <select class="edw-select" name="status" onchange="this.form.submit()">
                            <option value="">Tất cả trạng thái</option>
                            <?php foreach ($approvalLabels as $statusKey => $label): ?>
                                <option value="{{ $statusKey }}" @selected(request('status') === $statusKey)>{{ $label }}</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="edw-toolbar-actions">
                            <button class="edw-btn edw-btn-light edw-btn-sm" type="submit"><i class="bi bi-funnel"></i> Lọc</button>
                            <a class="edw-icon-btn" title="Đặt lại" href="{{ route('company-documents.index', ['department' => $department, 'scope' => $scope, 'folder' => $currentFolder?->id]) }}"><i class="bi bi-arrow-counterclockwise"></i></a>
                            <div class="edw-view-toggle">
                                <button id="edwListBtn" type="button" class="active" onclick="setEdwView('list')"><i class="bi bi-list-ul"></i></button>
                                <button id="edwGridBtn" type="button" onclick="setEdwView('grid')"><i class="bi bi-grid"></i></button>
                            </div>
                        </div>
                    </form>
                </div>

                <?php if ($companyDocClipboard): ?>
                    <div class="edw-clipboard">
                        <div><i class="bi bi-clipboard"></i> Đang {{ ($companyDocClipboard['action'] ?? '') === 'move' ? 'di chuyển' : 'sao chép' }}: <strong>{{ $companyDocClipboard['name'] ?? '' }}</strong></div>
                        <div style="display:flex;gap:7px">
                            <form method="post" action="{{ route('company-documents.paste') }}">@csrf<input type="hidden" name="department" value="{{ $department }}"><input type="hidden" name="folder_id" value="{{ $currentFolder?->id }}"><button class="edw-btn edw-btn-primary edw-btn-sm" type="submit"><i class="bi bi-clipboard-check"></i> Dán vào đây</button></form>
                            <form method="post" action="{{ route('company-documents.clipboard.clear') }}">@csrf<button class="edw-icon-btn" type="submit"><i class="bi bi-x-lg"></i></button></form>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="edw-list-head">
                    <div><h2>{{ $scopeMeta[$scope]['label'] ?? 'Danh sách hồ sơ' }}</h2><p>{{ $folders->count() }} thư mục · {{ $files->total() }} tài liệu phù hợp</p></div>
                    <?php if ($currentFolder && $scope === 'all'): ?>
                        <a class="edw-btn edw-btn-light edw-btn-sm" href="{{ route('company-documents.index', ['department' => $department, 'folder' => $currentFolder->parent_id]) }}"><i class="bi bi-arrow-left"></i> Lên một cấp</a>
                    <?php endif; ?>
                </div>

                <?php if ($folders->isEmpty() && $files->isEmpty()): ?>
                    <div class="edw-empty"><div class="edw-empty-icon"><i class="bi bi-folder2-open"></i></div><h3>Chưa có tài liệu trong phạm vi này</h3><div>Hãy tạo thư mục hoặc tải tài liệu đầu tiên lên đúng phòng ban.</div></div>
                <?php else: ?>
                    <div class="edw-table-wrap">
                        <table class="edw-table">
                            <thead><tr><th>Tên hồ sơ</th><th>Phòng ban</th><th>Người cập nhật</th><th>Cập nhật</th><th>Trạng thái</th><th style="text-align:right">Xử lý</th></tr></thead>
                            <tbody>
                                <?php foreach ($folders as $folder): ?>
                                    <tr>
                                        <td>
                                            <div class="edw-name-cell">
                                                <div class="edw-file-icon folder"><i class="bi bi-folder-fill"></i></div>
                                                <div style="min-width:0">
                                                    <div class="edw-item-title">{{ $folder->name }}</div>
                                                    <div class="edw-item-sub"><span>{{ ($folder->files_count ?? 0) }} file</span><span>{{ ($folder->children_count ?? 0) }} thư mục con</span><?php if ($folder->description): ?><span>{{ Str::limit($folder->description, 55) }}</span><?php endif; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $departmentMeta['short'] ?? $departmentMeta['label'] }}</td>
                                        <td>{{ $folder->creator?->name ?: 'Không xác định' }}</td>
                                        <td>{{ optional($folder->updated_at)->format('d/m/Y H:i') }}</td>
                                        <td><span class="edw-badge neutral">Thư mục</span></td>
                                        <td>
                                            <div class="edw-actions">
                                                <?php if ($scope === 'trash'): ?>
                                                    <form method="post" action="{{ route('company-documents.folders.restore', $folder->id) }}">@csrf<button class="edw-btn edw-btn-light edw-btn-sm" type="submit"><i class="bi bi-arrow-counterclockwise"></i> Khôi phục</button></form>
                                                <?php else: ?>
                                                    <a class="edw-btn edw-btn-light edw-btn-sm" href="{{ route('company-documents.index', ['department' => $department, 'folder' => $folder->id]) }}"><i class="bi bi-folder2-open"></i> Mở</a>
                                                    <details class="edw-menu"><summary class="edw-icon-btn"><i class="bi bi-three-dots"></i></summary><div class="edw-menu-panel">
                                                        <button type="button" data-url="{{ route('company-documents.folders.update', $folder) }}" data-name="{{ $folder->name }}" data-description="{{ $folder->description }}" onclick="openFolderEditFromButton(this)"><i class="bi bi-pencil"></i> Đổi tên / mô tả</button>
                                                        <form method="post" action="{{ route('company-documents.clipboard.set') }}">@csrf<input type="hidden" name="object_type" value="folder"><input type="hidden" name="object_id" value="{{ $folder->id }}"><input type="hidden" name="action" value="copy"><button type="submit"><i class="bi bi-files"></i> Sao chép</button></form>
                                                        <form method="post" action="{{ route('company-documents.clipboard.set') }}">@csrf<input type="hidden" name="object_type" value="folder"><input type="hidden" name="object_id" value="{{ $folder->id }}"><input type="hidden" name="action" value="move"><button type="submit"><i class="bi bi-arrows-move"></i> Di chuyển</button></form>
                                                        <form method="post" action="{{ route('company-documents.folders.destroy', $folder) }}" onsubmit="return confirm('Đưa thư mục và toàn bộ nội dung vào thùng rác? File vật lý sẽ không bị xóa.')">@csrf @method('DELETE')<button class="danger" type="submit"><i class="bi bi-trash3"></i> Đưa vào thùng rác</button></form>
                                                    </div></details>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php foreach ($files as $file): ?>
                                    <?php
                                        [$iconClass, $iconTone] = $fileIcon($file);
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="edw-name-cell">
                                                <div class="edw-file-icon {{ $iconTone }}"><i class="bi {{ $iconClass }}"></i></div>
                                                <div style="min-width:0">
                                                    <button type="button" class="edw-item-title" style="border:0;background:none;padding:0;text-align:left;cursor:pointer" data-details-url="{{ route('company-documents.files.show', $file) }}" onclick="openFileDrawer(this)">{{ $file->display_name }}</button>
                                                    <div class="edw-item-sub"><span>{{ strtoupper($file->extension ?: 'FILE') }}</span><span>{{ $formatBytes($file->size) }}</span><span>V{{ $file->version_no ?: 1 }}</span><?php if ($file->description): ?><span>{{ Str::limit($file->description, 58) }}</span><?php endif; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $departments[$file->department]['short'] ?? $file->department }}</td>
                                        <td>{{ $file->updater?->name ?: $file->uploader?->name ?: 'Không xác định' }}</td>
                                        <td>{{ optional($scope === 'trash' ? $file->deleted_at : $file->updated_at)->format('d/m/Y H:i') }}</td>
                                        <td><span class="edw-badge {{ $statusClasses[$file->approval_status] ?? 'neutral' }}">{{ $approvalLabels[$file->approval_status] ?? 'Không cần duyệt' }}</span></td>
                                        <td>
                                            <div class="edw-actions">
                                                <?php if ($scope === 'trash'): ?>
                                                    <form method="post" action="{{ route('company-documents.files.restore', $file->id) }}">@csrf<button class="edw-btn edw-btn-light edw-btn-sm" type="submit"><i class="bi bi-arrow-counterclockwise"></i> Khôi phục</button></form>
                                                <?php else: ?>
                                                    <button class="edw-btn edw-btn-light edw-btn-sm" type="button" data-details-url="{{ route('company-documents.files.show', $file) }}" onclick="openFileDrawer(this)"><i class="bi bi-eye"></i> Xem</button>
                                                    <details class="edw-menu"><summary class="edw-icon-btn"><i class="bi bi-three-dots"></i></summary><div class="edw-menu-panel">
                                                        <a href="{{ route('company-documents.files.download', $file) }}"><i class="bi bi-download"></i> Tải xuống</a>
                                                        <?php if (in_array($file->approval_status, ['draft','revision','not_required'], true)): ?>
                                                            <form method="post" action="{{ route('company-documents.files.submit', $file) }}">@csrf<button type="submit"><i class="bi bi-send"></i> Gửi duyệt</button></form>
                                                        <?php endif; ?>
                                                        <?php if ($canApproveDocuments && $file->approval_status === 'pending'): ?>
                                                            <form method="post" action="{{ route('company-documents.files.approve', $file) }}">@csrf<button type="submit"><i class="bi bi-check2-circle"></i> Phê duyệt</button></form>
                                                        <?php endif; ?>
                                                        <form method="post" action="{{ route('company-documents.clipboard.set') }}">@csrf<input type="hidden" name="object_type" value="file"><input type="hidden" name="object_id" value="{{ $file->id }}"><input type="hidden" name="action" value="copy"><button type="submit"><i class="bi bi-files"></i> Sao chép</button></form>
                                                        <form method="post" action="{{ route('company-documents.clipboard.set') }}">@csrf<input type="hidden" name="object_type" value="file"><input type="hidden" name="object_id" value="{{ $file->id }}"><input type="hidden" name="action" value="move"><button type="submit"><i class="bi bi-arrows-move"></i> Di chuyển</button></form>
                                                        <form method="post" action="{{ route('company-documents.files.destroy', $file) }}" onsubmit="return confirm('Đưa tài liệu vào thùng rác? File vật lý sẽ không bị xóa.')">@csrf @method('DELETE')<button class="danger" type="submit"><i class="bi bi-trash3"></i> Đưa vào thùng rác</button></form>
                                                    </div></details>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (method_exists($files, 'links')): ?><div class="edw-pagination">{{ $files->links() }}</div><?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>
</div>

<div id="folderModal" class="edw-modal" onclick="closeEdwModal(event,'folderModal')">
    <div class="edw-modal-box" onclick="event.stopPropagation()">
        <form method="post" action="{{ route('company-documents.folders.store') }}">@csrf
            <div class="edw-modal-head"><h3><i class="bi bi-folder-plus"></i> Tạo thư mục mới</h3><button class="edw-icon-btn" type="button" onclick="closeEdwModal(null,'folderModal')"><i class="bi bi-x-lg"></i></button></div>
            <div class="edw-modal-body"><input type="hidden" name="department" value="{{ $department }}"><input type="hidden" name="parent_id" value="{{ $currentFolder?->id }}"><div class="edw-form-grid"><div class="edw-field full"><label class="edw-label">Tên thư mục *</label><input class="edw-input" name="name" required placeholder="Ví dụ: Quy trình kỹ thuật"></div><div class="edw-field full"><label class="edw-label">Mô tả</label><textarea class="edw-input edw-textarea" name="description" placeholder="Mô tả ngắn nội dung lưu trong thư mục..."></textarea></div></div></div>
            <div class="edw-modal-foot"><button class="edw-btn edw-btn-light" type="button" onclick="closeEdwModal(null,'folderModal')">Hủy</button><button class="edw-btn edw-btn-primary" type="submit"><i class="bi bi-plus-lg"></i> Tạo thư mục</button></div>
        </form>
    </div>
</div>

<div id="uploadModal" class="edw-modal" onclick="closeEdwModal(event,'uploadModal')">
    <div class="edw-modal-box" onclick="event.stopPropagation()">
        <form method="post" action="{{ route('company-documents.files.upload') }}" enctype="multipart/form-data">@csrf
            <div class="edw-modal-head"><h3><i class="bi bi-cloud-arrow-up"></i> Tải tài liệu lên</h3><button class="edw-icon-btn" type="button" onclick="closeEdwModal(null,'uploadModal')"><i class="bi bi-x-lg"></i></button></div>
            <div class="edw-modal-body"><input type="hidden" name="department" value="{{ $department }}"><input type="hidden" name="folder_id" value="{{ $currentFolder?->id }}">
                <label class="edw-dropzone" for="edwFiles"><i class="bi bi-cloud-arrow-up"></i><strong>Kéo thả hoặc bấm để chọn nhiều file</strong><span>PDF, Word, Excel, PowerPoint, ảnh, video, ZIP · tối đa 50 MB/file</span><input id="edwFiles" type="file" name="files[]" multiple required hidden onchange="showSelectedFiles(this)"><div id="edwFileSelection" class="edw-file-selection"></div></label>
                <div class="edw-form-grid" style="margin-top:15px"><div class="edw-field full"><label class="edw-label">Mô tả chung</label><textarea class="edw-input edw-textarea" name="description" placeholder="Nội dung hoặc mục đích sử dụng..."></textarea></div><div class="edw-field"><label class="edw-label">Tags</label><input class="edw-input" name="tags" placeholder="quy trình, nội bộ, kho..."></div><div class="edw-field"><label class="edw-label">Ngày cần rà soát</label><input class="edw-input" type="date" name="review_due_at"></div><div class="edw-field full"><label class="edw-label">Luồng phê duyệt</label><select class="edw-select" name="approval_mode"><option value="not_required">Không cần duyệt</option><option value="draft">Lưu bản nháp</option><option value="pending">Gửi duyệt ngay</option></select></div></div>
            </div>
            <div class="edw-modal-foot"><button class="edw-btn edw-btn-light" type="button" onclick="closeEdwModal(null,'uploadModal')">Hủy</button><button class="edw-btn edw-btn-primary" type="submit"><i class="bi bi-cloud-arrow-up"></i> Tải lên</button></div>
        </form>
    </div>
</div>

<div id="folderEditModal" class="edw-modal" onclick="closeEdwModal(event,'folderEditModal')">
    <div class="edw-modal-box" onclick="event.stopPropagation()"><form id="folderEditForm" method="post">@csrf @method('PATCH')<div class="edw-modal-head"><h3>Chỉnh sửa thư mục</h3><button class="edw-icon-btn" type="button" onclick="closeEdwModal(null,'folderEditModal')"><i class="bi bi-x-lg"></i></button></div><div class="edw-modal-body"><div class="edw-field"><label class="edw-label">Tên thư mục</label><input id="folderEditName" class="edw-input" name="name" required></div><div class="edw-field" style="margin-top:12px"><label class="edw-label">Mô tả</label><textarea id="folderEditDescription" class="edw-input edw-textarea" name="description"></textarea></div></div><div class="edw-modal-foot"><button class="edw-btn edw-btn-light" type="button" onclick="closeEdwModal(null,'folderEditModal')">Hủy</button><button class="edw-btn edw-btn-primary" type="submit">Lưu thay đổi</button></div></form></div>
</div>

<div id="edwDrawerBackdrop" class="edw-drawer-backdrop" onclick="closeFileDrawer()"></div>
<aside id="edwDrawer" class="edw-drawer" role="dialog" aria-modal="true" aria-labelledby="drawerTitle">
    <div class="edw-drawer-head">
        <div class="edw-drawer-title-wrap">
            <div class="edw-drawer-file-icon"><i class="bi bi-file-earmark-text"></i></div>
            <h3 id="drawerTitle">Xem tài liệu</h3>
        </div>
        <div id="drawerActions" class="edw-drawer-actions"></div>
    </div>
    <div id="drawerBody" class="edw-drawer-body">
        <div class="edw-viewer-loading"><div class="edw-empty"><div class="edw-empty-icon"><i class="bi bi-hourglass-split"></i></div><h3>Đang tải tài liệu...</h3></div></div>
    </div>
    <div id="drawerEditPanel" class="edw-viewer-edit-panel"></div>
</aside>

<script>
function openEdwModal(id){document.getElementById(id)?.classList.add('show');document.body.style.overflow='hidden'}
function closeEdwModal(event,id){if(event&&event.target!==event.currentTarget)return;document.getElementById(id)?.classList.remove('show');document.body.style.overflow=''}
function showSelectedFiles(input){const el=document.getElementById('edwFileSelection');if(!el)return;const files=[...(input.files||[])];el.textContent=files.length?files.length+' file đã chọn: '+files.slice(0,3).map(f=>f.name).join(', ')+(files.length>3?'...':''):''}
function openFolderEditFromButton(btn){document.getElementById('folderEditForm').action=btn.dataset.url;document.getElementById('folderEditName').value=btn.dataset.name||'';document.getElementById('folderEditDescription').value=btn.dataset.description||'';openEdwModal('folderEditModal')}
function setEdwView(view){const card=document.getElementById('edwContentCard');const list=document.getElementById('edwListBtn');const grid=document.getElementById('edwGridBtn');card?.classList.toggle('edw-grid-mode',view==='grid');list?.classList.toggle('active',view==='list');grid?.classList.toggle('active',view==='grid');localStorage.setItem('egoCompanyDocView',view)}
document.addEventListener('DOMContentLoaded',()=>setEdwView(localStorage.getItem('egoCompanyDocView')||'list'));
function escapeHtml(value){return String(value??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]))}
function csrfInput(){return '<input type="hidden" name="_token" value="{{ csrf_token() }}">'}
const edwFileDetailCache=new Map();
async function openFileDrawer(btn){
    const drawer=document.getElementById('edwDrawer');
    const backdrop=document.getElementById('edwDrawerBackdrop');
    const body=document.getElementById('drawerBody');
    const actions=document.getElementById('drawerActions');
    const detailsUrl=btn.dataset.detailsUrl;
    drawer.classList.add('show');
    backdrop.classList.add('show');
    document.body.style.overflow='hidden';
    closeViewerEdit();
    actions.innerHTML='<button class="edw-icon-btn" type="button" onclick="closeFileDrawer()" title="Đóng"><i class="bi bi-x-lg"></i></button>';
    if(edwFileDetailCache.has(detailsUrl)){
        renderFileDrawer(edwFileDetailCache.get(detailsUrl));
        return;
    }
    body.innerHTML='<div class="edw-viewer-loading"><div class="edw-empty"><div class="edw-empty-icon"><i class="bi bi-hourglass-split"></i></div><h3>Đang tải tài liệu...</h3></div></div>';
    try{
        const res=await fetch(detailsUrl,{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
        if(!res.ok)throw new Error('Không tải được dữ liệu tài liệu');
        const d=await res.json();
        edwFileDetailCache.set(detailsUrl,d);
        renderFileDrawer(d);
    }catch(e){
        body.innerHTML='<div class="edw-viewer-loading"><div class="edw-alert edw-alert-error">'+escapeHtml(e.message)+'</div></div>';
    }
}
function closeFileDrawer(){
    document.getElementById('edwDrawer')?.classList.remove('show');
    document.getElementById('edwDrawerBackdrop')?.classList.remove('show');
    closeViewerEdit();
    document.body.style.overflow='';
}
function renderFileDrawer(d){
    document.getElementById('drawerTitle').textContent=d.name||'Xem tài liệu';
    const body=document.getElementById('drawerBody');
    const actions=document.getElementById('drawerActions');
    const editPanel=document.getElementById('drawerEditPanel');
    let actionHtml='<a class="edw-btn edw-btn-light edw-btn-sm" href="'+d.download_url+'" title="Tải xuống"><i class="bi bi-download"></i><span>Tải xuống</span></a>';
    if(d.can_edit){
        actionHtml+='<button class="edw-btn edw-btn-light edw-btn-sm" type="button" onclick="openViewerEdit()" title="Chỉnh sửa"><i class="bi bi-pencil"></i><span>Chỉnh sửa</span></button>';
    }
    actionHtml+='<button class="edw-icon-btn" type="button" onclick="closeFileDrawer()" title="Đóng"><i class="bi bi-x-lg"></i></button>';
    actions.innerHTML=actionHtml;
    const currentFrame=body.querySelector('iframe[data-preview-url]');
    if(!currentFrame||currentFrame.dataset.previewUrl!==d.preview_url){
        body.innerHTML='<div class="edw-preview"><iframe data-preview-url="'+escapeHtml(d.preview_url)+'" src="'+escapeHtml(d.preview_url)+'" title="'+escapeHtml(d.name||'Tài liệu')+'" loading="eager" referrerpolicy="same-origin"></iframe></div>';
    }
    editPanel.innerHTML=d.can_edit?buildViewerEditPanel(d):'';
}
function buildViewerEditPanel(d){
    return '<div class="edw-viewer-edit-head"><h4>Chỉnh sửa tài liệu</h4><button class="edw-icon-btn" type="button" onclick="closeViewerEdit()"><i class="bi bi-x-lg"></i></button></div>'+
    '<form method="post" action="'+d.update_url+'">'+csrfInput()+'<input type="hidden" name="_method" value="PATCH">'+
    '<div class="edw-field"><label class="edw-label">Tên hiển thị</label><input class="edw-input" name="name" value="'+escapeHtml(d.name)+'" required></div>'+
    '<div class="edw-field" style="margin-top:10px"><label class="edw-label">Mô tả</label><textarea class="edw-input edw-textarea" name="description">'+escapeHtml(d.description||'')+'</textarea></div>'+
    '<div class="edw-field" style="margin-top:10px"><label class="edw-label">Tags</label><input class="edw-input" name="tags" value="'+escapeHtml((d.tags||[]).join(', '))+'"></div>'+
    '<div class="edw-field" style="margin-top:10px"><label class="edw-label">Ngày rà soát</label><input class="edw-input" type="date" name="review_due_at"></div>'+
    '<button class="edw-btn edw-btn-primary" style="margin-top:12px" type="submit">Lưu thông tin</button></form>'+
    '<div class="edw-divider"></div><form method="post" action="'+d.version_upload_url+'" enctype="multipart/form-data">'+csrfInput()+'<div class="edw-field"><label class="edw-label">Tải phiên bản mới</label><input class="edw-input" type="file" name="file" required></div><div class="edw-field" style="margin-top:10px"><label class="edw-label">Nội dung thay đổi</label><textarea class="edw-input edw-textarea" name="change_note"></textarea></div><label style="display:flex;gap:8px;align-items:center;margin-top:10px;font-size:11.5px"><input type="checkbox" name="submit_after_upload" value="1"> Gửi duyệt ngay sau khi tải</label><button class="edw-btn edw-btn-light" style="margin-top:12px" type="submit"><i class="bi bi-upload"></i> Tải phiên bản</button></form>'+
    (d.can_approve&&d.approval_status==='pending'?'<div class="edw-divider"></div><form method="post" action="'+d.approve_url+'">'+csrfInput()+'<button class="edw-btn edw-btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Phê duyệt</button></form><form method="post" action="'+d.revision_url+'" style="margin-top:10px">'+csrfInput()+'<textarea class="edw-input edw-textarea" name="note" required placeholder="Lý do yêu cầu chỉnh sửa..."></textarea><button class="edw-btn edw-btn-danger" style="margin-top:8px" type="submit">Yêu cầu chỉnh sửa</button></form>':'');
}
function openViewerEdit(){document.getElementById('drawerEditPanel')?.classList.add('show')}
function closeViewerEdit(){document.getElementById('drawerEditPanel')?.classList.remove('show')}
document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeFileDrawer();document.querySelectorAll('.edw-modal.show').forEach(m=>m.classList.remove('show'));document.body.style.overflow=''}})
</script>
@endsection
