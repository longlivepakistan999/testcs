<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>旁站探测系统 - Side Site Detection</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
        }
        header h1 {
            text-align: center;
            font-size: 28px;
            font-weight: 600;
        }
        header p {
            text-align: center;
            opacity: 0.9;
            margin-top: 10px;
        }
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 20px;
        }
        .card h2 {
            font-size: 18px;
            margin-bottom: 20px;
            color: #444;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-box .number {
            font-size: 36px;
            font-weight: bold;
        }
        .stat-box .label {
            font-size: 14px;
            opacity: 0.9;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group textarea,
        .form-group input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-group textarea:focus,
        .form-group input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
        }
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        .btn-info:hover {
            background: #138496;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-pending { background: #ffc107; color: #333; }
        .badge-processing { background: #17a2b8; color: white; }
        .badge-completed { background: #28a745; color: white; }
        .badge-failed { background: #dc3545; color: white; }
        .badge-skipped { background: #6c757d; color: white; }
        .badge-shared { background: #ff6b6b; color: white; }
        .badge-dedicated { background: #4ecdc4; color: white; }
        .badge-unknown { background: #95a5a6; color: white; }
        .badge-cf { background: #f48024; color: white; }
        .badge-match { background: #28a745; color: white; }
        .badge-mismatch { background: #dc3545; color: white; }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        .pagination button {
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
            border-radius: 4px;
        }
        .pagination button:hover {
            background: #f8f9fa;
        }
        .pagination button.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-bar select {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        .text-muted {
            color: #999;
            font-size: 12px;
        }
        .text-success {
            color: #28a745;
        }
        .text-danger {
            color: #dc3545;
        }
        .ip-info {
            font-family: monospace;
            font-size: 12px;
        }
        .info-box {
            background: #e9ecef;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .info-box p {
            margin: 5px 0;
        }
        .export-section {
            margin-left: auto;
        }
        .batch-actions {
            background: #e9ecef;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            display: none;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .batch-actions.show {
            display: flex;
        }
        .batch-actions span {
            font-weight: 500;
            color: #667eea;
        }
        .checkbox-cell {
            width: 40px;
            text-align: center;
        }
        .checkbox-cell input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>旁站探测系统</h1>
            <p>Side Site Detection System - 市场调研分析工具</p>
        </div>
    </header>

    <div class="container">
        <!-- 统计卡片 -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-box">
                <div class="number" id="statTotal">0</div>
                <div class="label">总域名数</div>
            </div>
            <div class="stat-box">
                <div class="number" id="statCompleted">0</div>
                <div class="label">已完成</div>
            </div>
            <div class="stat-box">
                <div class="number" id="statShared">0</div>
                <div class="label">共享空间</div>
            </div>
            <div class="stat-box">
                <div class="number" id="statDedicated">0</div>
                <div class="label">独立服务器</div>
            </div>
        </div>

        <!-- 添加域名 -->
        <div class="card">
            <h2>添加域名</h2>

            <!-- 文件上传 -->
            <div class="form-group">
                <label>方式一：上传文件（TXT或CSV，每行一个域名）</label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="file" id="fileInput" accept=".txt,.csv" style="flex: 1;">
                    <button class="btn btn-primary" onclick="uploadFile()">上传导入</button>
                </div>
                <div id="uploadProgress" style="margin-top: 10px; display: none;">
                    <div style="background: #e9ecef; border-radius: 4px; height: 20px; overflow: hidden;">
                        <div id="progressBar" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); height: 100%; width: 0%; transition: width 0.3s;"></div>
                    </div>
                    <p id="progressText" style="margin-top: 5px; font-size: 12px; color: #666;"></p>
                </div>
            </div>

            <div style="text-align: center; margin: 15px 0; color: #999;">— 或者 —</div>

            <!-- 手动输入 -->
            <div class="form-group">
                <label for="domainInput">方式二：手动输入域名（每行一个或用逗号分隔）</label>
                <textarea id="domainInput" placeholder="example.com&#10;example.org&#10;example.net"></textarea>
            </div>
            <button class="btn btn-primary" onclick="addDomains()">添加域名</button>
            <button class="btn btn-success" onclick="processQueue()" style="margin-left: 10px;">开始检测队列</button>
        </div>

        <!-- 域名列表 -->
        <div class="card">
            <h2>域名列表</h2>
            <div class="filter-bar">
                <select id="statusFilter" onchange="loadDomains()">
                    <option value="">全部状态</option>
                    <option value="pending">待检测</option>
                    <option value="processing">检测中</option>
                    <option value="completed">已完成</option>
                    <option value="failed">失败</option>
                    <option value="skipped">已跳过</option>
                </select>
                <button class="btn btn-danger btn-sm" onclick="retryAllFailed()">重试全部失败</button>
                <div class="export-section">
                    <button class="btn btn-info btn-sm" onclick="exportDomains('csv')">导出域名CSV</button>
                    <button class="btn btn-warning btn-sm" onclick="exportAll('csv', false)">快速导出旁站</button>
                    <button class="btn btn-success btn-sm" onclick="exportAll('csv', true)">导出旁站(含IP检测)</button>
                </div>
            </div>

            <!-- 批量操作栏 -->
            <div class="batch-actions" id="batchActions">
                <span>已选择 <b id="selectedCount">0</b> 个</span>
                <button class="btn btn-success btn-sm" onclick="batchRetry()">重试选中</button>
                <button class="btn btn-info btn-sm" onclick="batchExport('csv')">导出选中CSV</button>
                <button class="btn btn-warning btn-sm" onclick="batchExport('json')">导出选中JSON</button>
                <button class="btn btn-danger btn-sm" onclick="batchDelete()">删除选中</button>
                <button class="btn btn-sm" onclick="clearSelection()">取消选择</button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th class="checkbox-cell"><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                        <th>ID</th>
                        <th>域名</th>
                        <th>IP地址</th>
                        <th>Cloudflare</th>
                        <th>旁站数</th>
                        <th>主机类型</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="domainList">
                    <tr><td colspan="9" class="loading">加载中...</td></tr>
                </tbody>
            </table>
            <div class="pagination" id="pagination"></div>
        </div>
    </div>

    <script>
        const API_URL = 'api.php';
        let currentPage = 1;
        let totalPages = 1;
        let selectedIds = new Set();

        // 初始化
        document.addEventListener('DOMContentLoaded', function() {
            loadStatistics();
            loadDomains();
        });

        // 加载统计数据
        async function loadStatistics() {
            try {
                const response = await fetch(`${API_URL}?action=statistics`);
                const result = await response.json();
                if (result.code === 0) {
                    const data = result.data;
                    document.getElementById('statTotal').textContent = data.total || 0;
                    document.getElementById('statCompleted').textContent = data.status?.completed || 0;
                    document.getElementById('statShared').textContent = data.hosting_type?.shared || 0;
                    document.getElementById('statDedicated').textContent = data.hosting_type?.dedicated || 0;
                }
            } catch (e) {
                console.error('加载统计失败', e);
            }
        }

        // 加载域名列表
        async function loadDomains(page = 1) {
            currentPage = page;
            const status = document.getElementById('statusFilter').value;
            const tbody = document.getElementById('domainList');
            tbody.innerHTML = '<tr><td colspan="9" class="loading">加载中...</td></tr>';

            try {
                let url = `${API_URL}?action=list&page=${page}&per_page=20`;
                if (status) url += `&status=${status}`;

                const response = await fetch(url);
                const result = await response.json();

                if (result.code === 0) {
                    const data = result.data;
                    totalPages = data.total_pages;
                    renderDomainList(data.data);
                    renderPagination();
                }
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="8" class="loading">加载失败</td></tr>';
                console.error('加载域名列表失败', e);
            }
        }

        // 渲染域名列表
        function renderDomainList(domains) {
            const tbody = document.getElementById('domainList');

            if (!domains || domains.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="loading">暂无数据</td></tr>';
                updateBatchActions();
                return;
            }

            tbody.innerHTML = domains.map(d => `
                <tr>
                    <td class="checkbox-cell">
                        <input type="checkbox" class="row-checkbox" data-id="${d.id}"
                            ${selectedIds.has(d.id) ? 'checked' : ''}
                            onchange="toggleSelect(${d.id})">
                    </td>
                    <td>${d.id}</td>
                    <td><strong>${escapeHtml(d.domain)}</strong></td>
                    <td class="ip-info">${d.ip_address || '<span class="text-muted">-</span>'}</td>
                    <td>${d.is_cloudflare == 1 ? '<span class="badge badge-cf">CF</span>' : '<span class="text-muted">-</span>'}</td>
                    <td>${d.side_site_count >= 0 && d.status === 'completed' ? `<a href="javascript:void(0)" onclick="viewSideSites(${d.id}, '${escapeHtml(d.domain)}')" style="color: #667eea; font-weight: bold;">${d.side_site_count}</a>` : '<span class="text-muted">-</span>'}</td>
                    <td>${getHostingTypeBadge(d.hosting_type)}</td>
                    <td>${getStatusBadge(d.status)}</td>
                    <td class="action-buttons">
                        ${d.status === 'pending' ? `<button class="btn btn-primary btn-sm" onclick="detectDomain(${d.id})">检测</button>` : ''}
                        ${d.status === 'failed' ? `<button class="btn btn-success btn-sm" onclick="retryDomain(${d.id})">重试</button>` : ''}
                        ${(d.side_site_count >= 0 && d.status === 'completed') ? `<button class="btn btn-info btn-sm" onclick="viewSideSites(${d.id}, '${escapeHtml(d.domain)}')">查看</button>` : ''}
                        <button class="btn btn-danger btn-sm" onclick="deleteDomain(${d.id})">删除</button>
                    </td>
                </tr>
            `).join('');

            updateBatchActions();
            updateSelectAllCheckbox();
        }

        // 切换单个选择
        function toggleSelect(id) {
            if (selectedIds.has(id)) {
                selectedIds.delete(id);
            } else {
                selectedIds.add(id);
            }
            updateBatchActions();
            updateSelectAllCheckbox();
        }

        // 全选/取消全选
        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            const selectAll = document.getElementById('selectAll').checked;

            checkboxes.forEach(cb => {
                const id = parseInt(cb.dataset.id);
                if (selectAll) {
                    selectedIds.add(id);
                    cb.checked = true;
                } else {
                    selectedIds.delete(id);
                    cb.checked = false;
                }
            });
            updateBatchActions();
        }

        // 更新全选复选框状态
        function updateSelectAllCheckbox() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            const selectAllCb = document.getElementById('selectAll');
            if (checkboxes.length === 0) {
                selectAllCb.checked = false;
                return;
            }
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            selectAllCb.checked = allChecked;
        }

        // 更新批量操作栏
        function updateBatchActions() {
            const batchActions = document.getElementById('batchActions');
            const selectedCount = document.getElementById('selectedCount');
            selectedCount.textContent = selectedIds.size;

            if (selectedIds.size > 0) {
                batchActions.classList.add('show');
            } else {
                batchActions.classList.remove('show');
            }
        }

        // 清除选择
        function clearSelection() {
            selectedIds.clear();
            document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAll').checked = false;
            updateBatchActions();
        }

        // 批量重试
        async function batchRetry() {
            if (selectedIds.size === 0) {
                alert('请先选择域名');
                return;
            }
            if (!confirm(`确定要重试选中的 ${selectedIds.size} 个域名吗？`)) return;

            try {
                const response = await fetch(`${API_URL}?action=batch_retry`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: Array.from(selectedIds) })
                });
                const result = await response.json();

                if (result.code === 0) {
                    alert(`已重置 ${result.data.count} 个域名为待检测状态`);
                    clearSelection();
                    loadStatistics();
                    loadDomains(currentPage);
                } else {
                    alert(result.message || '操作失败');
                }
            } catch (e) {
                alert('操作失败: ' + e.message);
            }
        }

        // 重试全部失败
        async function retryAllFailed() {
            if (!confirm('确定要重试所有失败的域名吗？')) return;

            try {
                const response = await fetch(`${API_URL}?action=retry_all_failed`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                });
                const result = await response.json();

                if (result.code === 0) {
                    alert(`已重置 ${result.data.count} 个失败的域名为待检测状态`);
                    loadStatistics();
                    loadDomains(currentPage);
                } else {
                    alert(result.message || '操作失败');
                }
            } catch (e) {
                alert('操作失败: ' + e.message);
            }
        }

        // 批量导出
        function batchExport(format) {
            if (selectedIds.size === 0) {
                alert('请先选择域名');
                return;
            }
            const ids = Array.from(selectedIds).join(',');
            window.open(`${API_URL}?action=batch_export&ids=${ids}&format=${format}`, '_blank');
        }

        // 批量删除
        async function batchDelete() {
            if (selectedIds.size === 0) {
                alert('请先选择域名');
                return;
            }
            if (!confirm(`确定要删除选中的 ${selectedIds.size} 个域名吗？此操作不可恢复！`)) return;

            try {
                const response = await fetch(`${API_URL}?action=batch_delete`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: Array.from(selectedIds) })
                });
                const result = await response.json();

                if (result.code === 0) {
                    alert(`已删除 ${result.data.count} 个域名`);
                    clearSelection();
                    loadStatistics();
                    loadDomains(currentPage);
                } else {
                    alert(result.message || '删除失败');
                }
            } catch (e) {
                alert('删除失败: ' + e.message);
            }
        }

        // 渲染分页
        function renderPagination() {
            const pagination = document.getElementById('pagination');
            let html = '';

            html += `<button onclick="loadDomains(1)" ${currentPage === 1 ? 'disabled' : ''}>首页</button>`;
            html += `<button onclick="loadDomains(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>上一页</button>`;
            html += `<button class="active">${currentPage} / ${totalPages || 1}</button>`;
            html += `<button onclick="loadDomains(${currentPage + 1})" ${currentPage === totalPages || totalPages === 0 ? 'disabled' : ''}>下一页</button>`;
            html += `<button onclick="loadDomains(${totalPages})" ${currentPage === totalPages || totalPages === 0 ? 'disabled' : ''}>末页</button>`;

            pagination.innerHTML = html;
        }

        // 添加域名
        async function addDomains() {
            const input = document.getElementById('domainInput').value.trim();
            if (!input) {
                alert('请输入域名');
                return;
            }

            try {
                const response = await fetch(`${API_URL}?action=add`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ domain: input })
                });
                const result = await response.json();

                if (result.code === 0) {
                    document.getElementById('domainInput').value = '';
                    loadStatistics();
                    loadDomains();
                    alert('添加成功');
                } else {
                    alert(result.message || '添加失败');
                }
            } catch (e) {
                alert('添加失败: ' + e.message);
            }
        }

        // 检测单个域名
        async function detectDomain(id) {
            try {
                // 设置60秒超时
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 60000);

                const response = await fetch(`${API_URL}?action=detect`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id }),
                    signal: controller.signal
                });
                clearTimeout(timeoutId);
                const result = await response.json();

                loadStatistics();
                loadDomains(currentPage);

                if (result.code === 0) {
                    const data = result.data;
                    if (data.is_cloudflare) {
                        alert(`检测完成：该域名使用Cloudflare CDN`);
                    } else {
                        alert(`检测完成：发现 ${data.side_site_count} 个旁站，判断为${data.hosting_type === 'shared' ? '共享空间' : '独立服务器'}`);
                    }
                } else {
                    alert(result.data?.message || result.message || '检测失败');
                }
            } catch (e) {
                if (e.name === 'AbortError') {
                    alert('检测超时，域名可能旁站过多。请稍后查看结果或使用CLI工具处理。');
                } else {
                    alert('检测失败: ' + e.message);
                }
                loadStatistics();
                loadDomains(currentPage);
            }
        }

        // 处理队列
        async function processQueue() {
            if (!confirm('确定要开始处理检测队列吗？')) return;

            try {
                // 设置较长的超时时间（5分钟）
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 300000);

                const response = await fetch(`${API_URL}?action=process`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ limit: 5 }),  // 每次处理5个，避免超时
                    signal: controller.signal
                });
                clearTimeout(timeoutId);
                const result = await response.json();

                loadStatistics();
                loadDomains(currentPage);

                if (result.code === 0) {
                    const results = result.data.results || [];
                    const success = results.filter(r => r.success).length;
                    alert(`队列处理完成：成功 ${success} 个，共 ${results.length} 个`);
                } else {
                    alert(result.message || '处理失败');
                }
            } catch (e) {
                if (e.name === 'AbortError') {
                    alert('处理超时，请尝试使用CLI工具处理大量域名：\nphp cli/process.php --batch=100');
                } else {
                    alert('处理失败: ' + e.message);
                }
            }
        }

        // 重试检测
        async function retryDomain(id) {
            try {
                const response = await fetch(`${API_URL}?action=retry`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                loadStatistics();
                loadDomains(currentPage);
            } catch (e) {
                alert('重试失败: ' + e.message);
            }
        }

        // 删除域名
        async function deleteDomain(id) {
            if (!confirm('确定要删除这个域名吗？')) return;

            try {
                const response = await fetch(`${API_URL}?action=delete`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                loadStatistics();
                loadDomains(currentPage);
            } catch (e) {
                alert('删除失败: ' + e.message);
            }
        }

        // 查看旁站列表 - 跳转到详情页
        function viewSideSites(id, domain) {
            window.location.href = `detail.php?id=${id}`;
        }

        // 导出单个域名的旁站
        function exportSingle(id, format) {
            window.open(`${API_URL}?action=export_single&id=${id}&format=${format}`, '_blank');
        }

        // 导出所有域名的旁站
        function exportAll(format, checkIp = false) {
            const status = document.getElementById('statusFilter').value || 'completed';
            const checkIpParam = checkIp ? '1' : '0';
            if (checkIp) {
                if (!confirm('带IP检测的导出会比较慢（需要DNS查询），确定继续吗？')) return;
            }
            window.open(`${API_URL}?action=export_all&format=${format}&status=${status}&check_ip=${checkIpParam}`, '_blank');
        }

        // 导出域名列表
        function exportDomains(format) {
            const status = document.getElementById('statusFilter').value || '';
            let url = `${API_URL}?action=export_domains&format=${format}`;
            if (status) url += `&status=${status}`;
            window.open(url, '_blank');
        }

        // 上传文件导入域名
        async function uploadFile() {
            const fileInput = document.getElementById('fileInput');
            const file = fileInput.files[0];

            if (!file) {
                alert('请选择文件');
                return;
            }

            const ext = file.name.split('.').pop().toLowerCase();
            if (!['txt', 'csv'].includes(ext)) {
                alert('只支持 TXT 或 CSV 文件');
                return;
            }

            // 显示进度
            const progressDiv = document.getElementById('uploadProgress');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            progressDiv.style.display = 'block';
            progressBar.style.width = '0%';
            progressText.textContent = '正在上传...';

            const formData = new FormData();
            formData.append('file', file);

            try {
                const xhr = new XMLHttpRequest();

                xhr.upload.onprogress = function(e) {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 50);
                        progressBar.style.width = percent + '%';
                        progressText.textContent = `上传中... ${percent}%`;
                    }
                };

                xhr.onload = function() {
                    progressBar.style.width = '100%';

                    if (xhr.status === 200) {
                        const result = JSON.parse(xhr.responseText);
                        if (result.code === 0) {
                            const data = result.data;
                            progressText.textContent = `导入完成！共 ${data.total} 个，新增 ${data.inserted} 个，跳过 ${data.skipped} 个，无效 ${data.invalid} 个`;
                            progressBar.style.background = '#28a745';
                            fileInput.value = '';
                            loadStatistics();
                            loadDomains();
                        } else {
                            progressText.textContent = '导入失败: ' + (result.message || '未知错误');
                            progressBar.style.background = '#dc3545';
                        }
                    } else {
                        progressText.textContent = '上传失败';
                        progressBar.style.background = '#dc3545';
                    }
                };

                xhr.onerror = function() {
                    progressText.textContent = '网络错误';
                    progressBar.style.background = '#dc3545';
                };

                xhr.open('POST', `${API_URL}?action=import`, true);
                xhr.send(formData);

                progressBar.style.width = '50%';
                progressText.textContent = '处理中...';

            } catch (e) {
                progressText.textContent = '错误: ' + e.message;
                progressBar.style.background = '#dc3545';
            }
        }

        // 状态徽章
        function getStatusBadge(status) {
            const badges = {
                'pending': '<span class="badge badge-pending">待检测</span>',
                'processing': '<span class="badge badge-processing">检测中</span>',
                'completed': '<span class="badge badge-completed">已完成</span>',
                'failed': '<span class="badge badge-failed">失败</span>',
                'skipped': '<span class="badge badge-skipped">已跳过</span>'
            };
            return badges[status] || status;
        }

        // 主机类型徽章
        function getHostingTypeBadge(type) {
            const badges = {
                'shared': '<span class="badge badge-shared">共享空间</span>',
                'dedicated': '<span class="badge badge-dedicated">独立服务器</span>',
                'unknown': '<span class="badge badge-unknown">未知</span>'
            };
            return badges[type] || '<span class="text-muted">-</span>';
        }

        // HTML转义
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
