<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>旁站探测系统 - Side Site Detection</title>
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #8b5cf6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-700: #374151;
            --gray-900: #111827;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--gray-100);
            color: var(--gray-700);
            line-height: 1.6;
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 24px; }

        /* Header */
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            padding: 24px 0;
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.3);
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo { display: flex; align-items: center; gap: 12px; color: white; }
        .logo-icon {
            width: 48px; height: 48px;
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
        }
        .logo h1 { font-size: 24px; font-weight: 700; }
        .logo p { font-size: 13px; opacity: 0.9; }

        /* Search Box */
        .search-box {
            display: flex;
            background: rgba(255,255,255,0.15);
            border-radius: 12px;
            padding: 4px;
            width: 400px;
        }
        .search-box input {
            flex: 1;
            background: transparent;
            border: none;
            padding: 12px 16px;
            color: white;
            font-size: 14px;
        }
        .search-box input::placeholder { color: rgba(255,255,255,0.7); }
        .search-box input:focus { outline: none; }
        .search-box button {
            background: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            color: var(--primary);
            transition: all 0.2s;
        }
        .search-box button:hover { transform: scale(1.02); }

        /* Main Content */
        .main { padding: 24px 0; }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .stat-card .icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; margin-bottom: 12px;
        }
        .stat-card .icon.purple { background: rgba(99, 102, 241, 0.1); color: var(--primary); }
        .stat-card .icon.green { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .stat-card .icon.orange { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .stat-card .icon.red { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .stat-card .icon.blue { background: rgba(59, 130, 246, 0.1); color: var(--info); }
        .stat-card .number { font-size: 28px; font-weight: 700; color: var(--gray-900); }
        .stat-card .label { font-size: 13px; color: var(--gray-500); margin-top: 4px; }

        /* Cards */
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 24px;
            overflow: hidden;
        }
        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-body { padding: 24px; }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #059669; }
        .btn-info { background: var(--info); color: white; }
        .btn-info:hover { background: #2563eb; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-warning:hover { background: #d97706; }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--gray-300);
            color: var(--gray-700);
        }
        .btn-outline:hover { background: var(--gray-100); }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-group { display: flex; gap: 8px; flex-wrap: wrap; }

        /* Forms */
        .form-row { display: flex; gap: 16px; margin-bottom: 16px; }
        .form-group { flex: 1; }
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 6px;
            color: var(--gray-700);
        }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        textarea.form-control { min-height: 100px; resize: vertical; }
        select.form-control { cursor: pointer; }

        /* Table */
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }
        th {
            background: var(--gray-50);
            font-weight: 600;
            font-size: 13px;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        tr:hover { background: var(--gray-50); }
        .loading { text-align: center; padding: 40px; color: var(--gray-500); }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-success { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .badge-danger { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .badge-warning { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .badge-info { background: rgba(59, 130, 246, 0.1); color: var(--info); }
        .badge-purple { background: rgba(139, 92, 246, 0.1); color: var(--secondary); }
        .badge-gray { background: var(--gray-100); color: var(--gray-500); }

        /* Batch Actions */
        .batch-bar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 16px 24px;
            border-radius: 12px;
            margin-bottom: 16px;
            display: none;
            align-items: center;
            gap: 16px;
        }
        .batch-bar.show { display: flex; }
        .batch-bar .count { font-weight: 600; font-size: 15px; }
        .batch-bar .btn { background: rgba(255,255,255,0.2); }
        .batch-bar .btn:hover { background: rgba(255,255,255,0.3); }

        /* Checkbox */
        .checkbox-cell { width: 40px; text-align: center; }
        .checkbox-cell input[type="checkbox"] {
            width: 18px; height: 18px;
            cursor: pointer;
            accent-color: var(--primary);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 20px 0;
        }
        .pagination button {
            padding: 8px 14px;
            border: 1px solid var(--gray-300);
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        .pagination button:hover { background: var(--gray-100); }
        .pagination button.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .pagination button:disabled { opacity: 0.5; cursor: not-allowed; }
        .pagination .page-info { color: var(--gray-500); font-size: 14px; }

        /* Search Results Modal */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-overlay.show { display: flex; }
        .modal {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 700px;
            max-height: 80vh;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
        }
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 { font-size: 18px; font-weight: 600; }
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray-500);
        }
        .modal-body { padding: 24px; overflow-y: auto; max-height: 60vh; }

        /* Search Result Items */
        .search-result {
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            transition: all 0.2s;
        }
        .search-result:hover { border-color: var(--primary); background: var(--gray-50); }
        .search-result .domain { font-weight: 600; color: var(--gray-900); font-size: 15px; }
        .search-result .info { font-size: 13px; color: var(--gray-500); margin-top: 6px; }
        .search-result .type { margin-top: 8px; }

        /* File Upload */
        .file-upload {
            border: 2px dashed var(--gray-300);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .file-upload:hover { border-color: var(--primary); background: var(--gray-50); }
        .file-upload input { display: none; }
        .file-upload .icon { font-size: 40px; color: var(--gray-400); margin-bottom: 12px; }
        .file-upload p { color: var(--gray-500); }

        /* Toolbar */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .toolbar-left, .toolbar-right { display: flex; gap: 12px; align-items: center; }

        /* Action Buttons */
        .action-buttons { display: flex; gap: 6px; }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .header-content { flex-direction: column; gap: 16px; }
            .search-box { width: 100%; }
            .form-row { flex-direction: column; }
            .toolbar { flex-direction: column; align-items: stretch; }
        }

        /* Animations */
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .card { animation: fadeIn 0.3s ease; }

        /* Text utilities */
        .text-muted { color: var(--gray-500); }
        .text-success { color: var(--success); }
        .text-danger { color: var(--danger); }
        .font-mono { font-family: 'SF Mono', Monaco, monospace; font-size: 13px; }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <div class="logo-icon">&#127760;</div>
                    <div>
                        <h1>旁站探测系统</h1>
                        <p>Side Site Detection System</p>
                    </div>
                </div>
                <div class="search-box">
                    <input type="text" id="globalSearch" placeholder="搜索域名或IP地址..." onkeypress="if(event.key==='Enter')searchGlobal()">
                    <button onclick="searchGlobal()">搜索</button>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main">
        <div class="container">
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="icon purple">&#128202;</div>
                    <div class="number" id="statTotal">0</div>
                    <div class="label">总域名数</div>
                </div>
                <div class="stat-card">
                    <div class="icon green">&#9989;</div>
                    <div class="number" id="statCompleted">0</div>
                    <div class="label">已完成</div>
                </div>
                <div class="stat-card">
                    <div class="icon orange">&#9203;</div>
                    <div class="number" id="statPending">0</div>
                    <div class="label">待检测</div>
                </div>
                <div class="stat-card">
                    <div class="icon red">&#10060;</div>
                    <div class="number" id="statFailed">0</div>
                    <div class="label">失败</div>
                </div>
                <div class="stat-card">
                    <div class="icon blue">&#128279;</div>
                    <div class="number" id="statSideSites">0</div>
                    <div class="label">旁站总数</div>
                </div>
            </div>

            <!-- Add Domain Card -->
            <div class="card">
                <div class="card-header">
                    <h2>&#10133; 添加域名</h2>
                    <div class="btn-group">
                        <label class="btn btn-outline btn-sm" style="cursor: pointer;">
                            &#128194; 上传文件
                            <input type="file" id="fileUpload" accept=".txt,.csv" onchange="uploadFile(this)" style="display:none">
                        </label>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group" style="flex: 3;">
                            <label>输入域名（支持批量，每行一个）</label>
                            <textarea id="domainInput" class="form-control" placeholder="example.com&#10;test.com&#10;demo.org"></textarea>
                        </div>
                        <div class="form-group" style="flex: 1; display: flex; flex-direction: column; justify-content: flex-end;">
                            <button class="btn btn-primary" onclick="addDomains()" style="height: 100px;">
                                &#10133; 添加域名
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Domain List Card -->
            <div class="card">
                <div class="card-header">
                    <h2>&#128203; 域名列表</h2>
                    <button class="btn btn-success" onclick="processQueue()">&#9654; 开始检测队列</button>
                </div>
                <div class="card-body">
                    <!-- Toolbar -->
                    <div class="toolbar">
                        <div class="toolbar-left">
                            <select id="statusFilter" class="form-control" style="width: auto;" onchange="loadDomains()">
                                <option value="">全部状态</option>
                                <option value="pending">待检测</option>
                                <option value="processing">检测中</option>
                                <option value="completed">已完成</option>
                                <option value="failed">失败</option>
                                <option value="skipped">已跳过</option>
                            </select>
                            <button class="btn btn-danger btn-sm" onclick="retryAllFailed()">&#128260; 重试全部失败</button>
                        </div>
                        <div class="toolbar-right">
                            <button class="btn btn-outline btn-sm" onclick="exportDomains('csv')">&#128229; 导出域名</button>
                            <button class="btn btn-info btn-sm" onclick="exportAll('csv', false)">&#128229; 快速导出旁站</button>
                            <button class="btn btn-success btn-sm" onclick="exportAll('csv', true)">&#128229; 完整导出</button>
                        </div>
                    </div>

                    <!-- Batch Actions -->
                    <div class="batch-bar" id="batchActions">
                        <span class="count">已选择 <b id="selectedCount">0</b> 个</span>
                        <button class="btn btn-sm" onclick="batchRetry()">&#128260; 重试</button>
                        <button class="btn btn-sm" onclick="batchExport('csv')">&#128229; 导出CSV</button>
                        <button class="btn btn-sm" onclick="batchExport('json')">&#128229; 导出JSON</button>
                        <button class="btn btn-sm" onclick="batchDelete()">&#128465; 删除</button>
                        <button class="btn btn-sm" onclick="clearSelection()">&#10006; 取消</button>
                    </div>

                    <!-- Table -->
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th class="checkbox-cell"><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                                    <th>ID</th>
                                    <th>域名</th>
                                    <th>IP地址</th>
                                    <th>CDN</th>
                                    <th>旁站数</th>
                                    <th>类型</th>
                                    <th>状态</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody id="domainList">
                                <tr><td colspan="9" class="loading">加载中...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination" id="pagination"></div>
                </div>
            </div>
        </div>
    </main>

    <!-- Search Results Modal -->
    <div class="modal-overlay" id="searchModal">
        <div class="modal">
            <div class="modal-header">
                <h3>&#128269; 搜索结果</h3>
                <button class="modal-close" onclick="closeSearchModal()">&times;</button>
            </div>
            <div class="modal-body" id="searchResults">
                <p class="text-muted">请输入域名或IP地址进行搜索</p>
            </div>
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
                    document.getElementById('statCompleted').textContent = data.completed || 0;
                    document.getElementById('statPending').textContent = data.pending || 0;
                    document.getElementById('statFailed').textContent = data.failed || 0;
                    document.getElementById('statSideSites').textContent = data.side_sites || 0;
                }
            } catch (e) {
                console.error('加载统计失败:', e);
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
                    totalPages = result.data.total_pages || 1;
                    renderDomainList(result.data.data || result.data.domains || []);
                    renderPagination();
                }
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="9" class="loading">加载失败</td></tr>';
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
                            ${selectedIds.has(d.id) ? 'checked' : ''} onchange="toggleSelect(${d.id})">
                    </td>
                    <td>${d.id}</td>
                    <td><strong>${escapeHtml(d.domain)}</strong></td>
                    <td class="font-mono">${d.ip_address || '<span class="text-muted">-</span>'}</td>
                    <td>${d.is_cloudflare == 1 ? '<span class="badge badge-warning">CF</span>' : '<span class="text-muted">-</span>'}</td>
                    <td>${d.side_site_count >= 0 && d.status === 'completed' ?
                        `<a href="detail.php?id=${d.id}" style="color: var(--primary); font-weight: 600;">${d.side_site_count}</a>` :
                        '<span class="text-muted">-</span>'}</td>
                    <td>${getHostingTypeBadge(d.hosting_type)}</td>
                    <td>${getStatusBadge(d.status)}</td>
                    <td class="action-buttons">
                        ${d.status === 'pending' ? `<button class="btn btn-primary btn-sm" onclick="detectDomain(${d.id})">检测</button>` : ''}
                        ${d.status === 'failed' ? `<button class="btn btn-success btn-sm" onclick="retryDomain(${d.id})">重试</button>` : ''}
                        ${d.status === 'completed' ? `<a href="detail.php?id=${d.id}" class="btn btn-info btn-sm">查看</a>` : ''}
                        <button class="btn btn-danger btn-sm" onclick="deleteDomain(${d.id})">删除</button>
                    </td>
                </tr>
            `).join('');

            updateBatchActions();
            updateSelectAllCheckbox();
        }

        // 获取状态徽章
        function getStatusBadge(status) {
            const badges = {
                'pending': '<span class="badge badge-gray">待检测</span>',
                'processing': '<span class="badge badge-info">检测中</span>',
                'completed': '<span class="badge badge-success">已完成</span>',
                'failed': '<span class="badge badge-danger">失败</span>',
                'skipped': '<span class="badge badge-warning">已跳过</span>',
            };
            return badges[status] || '<span class="badge badge-gray">未知</span>';
        }

        // 获取主机类型徽章
        function getHostingTypeBadge(type) {
            const badges = {
                'shared': '<span class="badge badge-danger">共享空间</span>',
                'dedicated': '<span class="badge badge-success">独立服务器</span>',
                'unknown': '<span class="badge badge-gray">未知</span>',
            };
            return badges[type] || '<span class="badge badge-gray">-</span>';
        }

        // 渲染分页
        function renderPagination() {
            const pagination = document.getElementById('pagination');
            let html = '';

            html += `<button onclick="loadDomains(1)" ${currentPage === 1 ? 'disabled' : ''}>首页</button>`;
            html += `<button onclick="loadDomains(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>上一页</button>`;
            html += `<button class="active">${currentPage} / ${totalPages || 1}</button>`;
            html += `<button onclick="loadDomains(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>下一页</button>`;
            html += `<button onclick="loadDomains(${totalPages})" ${currentPage === totalPages ? 'disabled' : ''}>末页</button>`;

            pagination.innerHTML = html;
        }

        // 全局搜索
        async function searchGlobal() {
            const query = document.getElementById('globalSearch').value.trim();
            if (!query) {
                alert('请输入搜索内容');
                return;
            }

            document.getElementById('searchModal').classList.add('show');
            document.getElementById('searchResults').innerHTML = '<p class="loading">搜索中...</p>';

            try {
                const response = await fetch(`${API_URL}?action=search&q=${encodeURIComponent(query)}`);
                const result = await response.json();

                if (result.code === 0) {
                    renderSearchResults(result.data, query);
                } else {
                    document.getElementById('searchResults').innerHTML = '<p class="text-muted">搜索失败</p>';
                }
            } catch (e) {
                document.getElementById('searchResults').innerHTML = '<p class="text-muted">搜索出错: ' + e.message + '</p>';
            }
        }

        // 渲染搜索结果
        function renderSearchResults(data, query) {
            const container = document.getElementById('searchResults');
            let html = '';

            if (data.domains && data.domains.length > 0) {
                html += '<h4 style="margin-bottom: 12px;">&#127760; 主域名匹配</h4>';
                data.domains.forEach(d => {
                    html += `
                        <div class="search-result">
                            <div class="domain">${escapeHtml(d.domain)}</div>
                            <div class="info">IP: ${d.ip_address || '-'} | 旁站数: ${d.side_site_count || 0}</div>
                            <div class="type">${getStatusBadge(d.status)} ${getHostingTypeBadge(d.hosting_type)}</div>
                            <div style="margin-top: 10px;">
                                <a href="detail.php?id=${d.id}" class="btn btn-info btn-sm">查看详情</a>
                            </div>
                        </div>
                    `;
                });
            }

            if (data.side_sites && data.side_sites.length > 0) {
                html += '<h4 style="margin: 20px 0 12px;">&#128279; 旁站匹配</h4>';
                data.side_sites.forEach(s => {
                    html += `
                        <div class="search-result">
                            <div class="domain">${escapeHtml(s.side_domain)}</div>
                            <div class="info">
                                属于主域名: <strong>${escapeHtml(s.main_domain)}</strong><br>
                                原始IP: ${s.ip_address || '-'} | 当前IP: ${s.current_ip || '-'}
                            </div>
                            <div class="type">
                                ${s.ip_match ? '<span class="badge badge-success">IP一致</span>' : '<span class="badge badge-danger">IP不一致</span>'}
                            </div>
                            <div style="margin-top: 10px;">
                                <a href="detail.php?id=${s.domain_id}" class="btn btn-info btn-sm">查看主域名</a>
                            </div>
                        </div>
                    `;
                });
            }

            if (!html) {
                html = `<p class="text-muted">未找到与 "${escapeHtml(query)}" 相关的结果</p>`;
            }

            container.innerHTML = html;
        }

        // 关闭搜索模态框
        function closeSearchModal() {
            document.getElementById('searchModal').classList.remove('show');
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

        // 上传文件
        async function uploadFile(input) {
            const file = input.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('file', file);

            try {
                const response = await fetch(`${API_URL}?action=import`, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.code === 0) {
                    alert(`导入完成: 新增 ${result.data.inserted} 个, 跳过 ${result.data.skipped} 个, 无效 ${result.data.invalid} 个`);
                    loadStatistics();
                    loadDomains();
                } else {
                    alert(result.message || '导入失败');
                }
            } catch (e) {
                alert('导入失败: ' + e.message);
            }
            input.value = '';
        }

        // 检测域名
        async function detectDomain(id) {
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 60000);

                const response = await fetch(`${API_URL}?action=detect`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id }),
                    signal: controller.signal
                });
                clearTimeout(timeoutId);
                const result = await response.json();

                loadStatistics();
                loadDomains(currentPage);

                if (result.code === 0) {
                    const data = result.data;
                    if (data.is_cloudflare) {
                        alert('检测完成：使用Cloudflare CDN');
                    } else {
                        alert(`检测完成：发现 ${data.side_site_count} 个旁站`);
                    }
                } else {
                    alert(result.message || '检测失败');
                }
            } catch (e) {
                if (e.name === 'AbortError') {
                    alert('检测超时，请使用CLI工具处理');
                } else {
                    alert('检测失败: ' + e.message);
                }
                loadDomains(currentPage);
            }
        }

        // 处理队列
        async function processQueue() {
            if (!confirm('确定开始处理检测队列吗？')) return;

            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 300000);

                const response = await fetch(`${API_URL}?action=process`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ limit: 5 }),
                    signal: controller.signal
                });
                clearTimeout(timeoutId);
                const result = await response.json();

                loadStatistics();
                loadDomains(currentPage);

                if (result.code === 0) {
                    const results = result.data.results || [];
                    alert(`处理完成：成功 ${results.filter(r => r.success).length} 个`);
                } else {
                    alert(result.message || '处理失败');
                }
            } catch (e) {
                if (e.name === 'AbortError') {
                    alert('处理超时，请使用CLI工具：\nphp cli/process.php --batch=100');
                } else {
                    alert('处理失败: ' + e.message);
                }
            }
        }

        // 重试域名
        async function retryDomain(id) {
            try {
                const response = await fetch(`${API_URL}?action=retry`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                loadStatistics();
                loadDomains(currentPage);
            } catch (e) {
                alert('重试失败: ' + e.message);
            }
        }

        // 删除域名
        async function deleteDomain(id) {
            if (!confirm('确定删除此域名？')) return;

            try {
                const response = await fetch(`${API_URL}?action=delete`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                loadStatistics();
                loadDomains(currentPage);
            } catch (e) {
                alert('删除失败: ' + e.message);
            }
        }

        // 选择相关功能
        function toggleSelect(id) {
            if (selectedIds.has(id)) selectedIds.delete(id);
            else selectedIds.add(id);
            updateBatchActions();
            updateSelectAllCheckbox();
        }

        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            const selectAll = document.getElementById('selectAll').checked;
            checkboxes.forEach(cb => {
                const id = parseInt(cb.dataset.id);
                if (selectAll) { selectedIds.add(id); cb.checked = true; }
                else { selectedIds.delete(id); cb.checked = false; }
            });
            updateBatchActions();
        }

        function updateSelectAllCheckbox() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            const selectAllCb = document.getElementById('selectAll');
            if (checkboxes.length === 0) { selectAllCb.checked = false; return; }
            selectAllCb.checked = Array.from(checkboxes).every(cb => cb.checked);
        }

        function updateBatchActions() {
            const bar = document.getElementById('batchActions');
            document.getElementById('selectedCount').textContent = selectedIds.size;
            bar.classList.toggle('show', selectedIds.size > 0);
        }

        function clearSelection() {
            selectedIds.clear();
            document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAll').checked = false;
            updateBatchActions();
        }

        // 批量操作
        async function batchRetry() {
            if (!confirm(`重试选中的 ${selectedIds.size} 个域名？`)) return;
            try {
                await fetch(`${API_URL}?action=batch_retry`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: Array.from(selectedIds) })
                });
                clearSelection();
                loadStatistics();
                loadDomains(currentPage);
            } catch (e) { alert('操作失败'); }
        }

        async function retryAllFailed() {
            if (!confirm('重试所有失败的域名？')) return;
            try {
                await fetch(`${API_URL}?action=retry_all_failed`, { method: 'POST' });
                loadStatistics();
                loadDomains(currentPage);
            } catch (e) { alert('操作失败'); }
        }

        function batchExport(format) {
            if (selectedIds.size === 0) return;
            window.open(`${API_URL}?action=batch_export&ids=${Array.from(selectedIds).join(',')}&format=${format}`);
        }

        async function batchDelete() {
            if (!confirm(`删除选中的 ${selectedIds.size} 个域名？`)) return;
            try {
                await fetch(`${API_URL}?action=batch_delete`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: Array.from(selectedIds) })
                });
                clearSelection();
                loadStatistics();
                loadDomains(currentPage);
            } catch (e) { alert('删除失败'); }
        }

        // 导出功能
        function exportDomains(format) {
            const status = document.getElementById('statusFilter').value;
            let url = `${API_URL}?action=export_domains&format=${format}`;
            if (status) url += `&status=${status}`;
            window.open(url);
        }

        function exportAll(format, checkIp) {
            if (checkIp && !confirm('完整导出需要更长时间，确定继续？')) return;
            const status = document.getElementById('statusFilter').value || 'completed';
            window.open(`${API_URL}?action=export_all&format=${format}&status=${status}&check_ip=${checkIp ? 1 : 0}`);
        }

        // 工具函数
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // 点击模态框外部关闭
        document.getElementById('searchModal').addEventListener('click', function(e) {
            if (e.target === this) closeSearchModal();
        });
    </script>
</body>
</html>
