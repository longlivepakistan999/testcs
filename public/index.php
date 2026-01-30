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
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal.show {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 10px;
            width: 95%;
            max-width: 1000px;
            max-height: 85vh;
            overflow: auto;
        }
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }
        .modal-header h3 {
            font-size: 18px;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        .modal-body {
            padding: 20px;
        }
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            position: sticky;
            bottom: 0;
            background: white;
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
            <div class="form-group">
                <label for="domainInput">输入域名（支持批量，每行一个或用逗号分隔）</label>
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
                <div class="export-section">
                    <button class="btn btn-info btn-sm" onclick="exportAll('csv')">导出全部CSV</button>
                    <button class="btn btn-info btn-sm" onclick="exportAll('json')">导出全部JSON</button>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
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
                    <tr><td colspan="8" class="loading">加载中...</td></tr>
                </tbody>
            </table>
            <div class="pagination" id="pagination"></div>
        </div>
    </div>

    <!-- 旁站列表弹窗 -->
    <div class="modal" id="sideSiteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">旁站列表</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="loading">加载中...</div>
            </div>
            <div class="modal-footer" id="modalFooter" style="display: none;">
                <button class="btn btn-info" id="exportCsvBtn">导出CSV</button>
                <button class="btn btn-info" id="exportJsonBtn">导出JSON</button>
                <button class="btn btn-primary" onclick="closeModal()">关闭</button>
            </div>
        </div>
    </div>

    <script>
        const API_URL = 'api.php';
        let currentPage = 1;
        let totalPages = 1;
        let currentViewingDomainId = null;

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
            tbody.innerHTML = '<tr><td colspan="8" class="loading">加载中...</td></tr>';

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
                tbody.innerHTML = '<tr><td colspan="8" class="loading">暂无数据</td></tr>';
                return;
            }

            tbody.innerHTML = domains.map(d => `
                <tr>
                    <td>${d.id}</td>
                    <td><strong>${escapeHtml(d.domain)}</strong></td>
                    <td class="ip-info">${d.ip_address || '<span class="text-muted">-</span>'}</td>
                    <td>${d.is_cloudflare == 1 ? '<span class="badge badge-cf">CF</span>' : '<span class="text-muted">-</span>'}</td>
                    <td>${d.side_site_count > 0 ? `<a href="javascript:void(0)" onclick="viewSideSites(${d.id}, '${escapeHtml(d.domain)}')" style="color: #667eea; font-weight: bold;">${d.side_site_count}</a>` : '<span class="text-muted">-</span>'}</td>
                    <td>${getHostingTypeBadge(d.hosting_type)}</td>
                    <td>${getStatusBadge(d.status)}</td>
                    <td class="action-buttons">
                        ${d.status === 'pending' ? `<button class="btn btn-primary btn-sm" onclick="detectDomain(${d.id})">检测</button>` : ''}
                        ${d.status === 'failed' ? `<button class="btn btn-success btn-sm" onclick="retryDomain(${d.id})">重试</button>` : ''}
                        ${d.side_site_count > 0 ? `<button class="btn btn-info btn-sm" onclick="viewSideSites(${d.id}, '${escapeHtml(d.domain)}')">查看</button>` : ''}
                        <button class="btn btn-danger btn-sm" onclick="deleteDomain(${d.id})">删除</button>
                    </td>
                </tr>
            `).join('');
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
                const response = await fetch(`${API_URL}?action=detect`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
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
                alert('检测失败: ' + e.message);
            }
        }

        // 处理队列
        async function processQueue() {
            if (!confirm('确定要开始处理检测队列吗？')) return;

            try {
                const response = await fetch(`${API_URL}?action=process`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ limit: 10 })
                });
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
                alert('处理失败: ' + e.message);
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

        // 查看旁站列表
        async function viewSideSites(id, domain) {
            currentViewingDomainId = id;
            document.getElementById('modalTitle').textContent = `${domain} 的旁站列表`;
            document.getElementById('modalBody').innerHTML = '<div class="loading">正在加载并检测旁站IP...</div>';
            document.getElementById('modalFooter').style.display = 'none';
            document.getElementById('sideSiteModal').classList.add('show');

            try {
                const response = await fetch(`${API_URL}?action=side_sites&id=${id}&check_ip=1`);
                const result = await response.json();

                if (result.code === 0) {
                    const data = result.data.data || result.data;
                    const domainInfo = data.domain;
                    const sites = data.sites || [];
                    const originalIp = data.original_ip || domainInfo?.ip_address;

                    if (sites.length === 0) {
                        document.getElementById('modalBody').innerHTML = '<p>暂无旁站数据</p>';
                        return;
                    }

                    // 统计IP匹配情况
                    const matchCount = sites.filter(s => s.ip_match).length;
                    const mismatchCount = sites.length - matchCount;

                    let html = `
                        <div class="info-box">
                            <p><strong>原始IP地址：</strong> <code>${originalIp || '-'}</code></p>
                            <p><strong>旁站总数：</strong> ${sites.length}</p>
                            <p><strong>IP一致：</strong> <span class="text-success">${matchCount}</span> |
                               <strong>IP不一致：</strong> <span class="text-danger">${mismatchCount}</span></p>
                        </div>
                    `;

                    html += '<table><thead><tr><th>#</th><th>旁站域名</th><th>原始IP</th><th>当前IP</th><th>IP状态</th><th>最后解析</th></tr></thead><tbody>';
                    sites.forEach((site, index) => {
                        const ipMatch = site.ip_match;
                        html += `<tr>
                            <td>${index + 1}</td>
                            <td><a href="http://${escapeHtml(site.side_domain)}" target="_blank">${escapeHtml(site.side_domain)}</a></td>
                            <td class="ip-info">${site.ip_address || '-'}</td>
                            <td class="ip-info ${ipMatch ? 'text-success' : 'text-danger'}">${site.current_ip || '-'}</td>
                            <td>${ipMatch ? '<span class="badge badge-match">一致</span>' : '<span class="badge badge-mismatch">不一致</span>'}</td>
                            <td>${site.last_resolved || '-'}</td>
                        </tr>`;
                    });
                    html += '</tbody></table>';

                    document.getElementById('modalBody').innerHTML = html;

                    // 显示导出按钮
                    document.getElementById('modalFooter').style.display = 'flex';
                    document.getElementById('exportCsvBtn').onclick = () => exportSingle(id, 'csv');
                    document.getElementById('exportJsonBtn').onclick = () => exportSingle(id, 'json');
                } else {
                    document.getElementById('modalBody').innerHTML = '<p>加载失败</p>';
                }
            } catch (e) {
                document.getElementById('modalBody').innerHTML = '<p>加载失败: ' + e.message + '</p>';
            }
        }

        // 导出单个域名的旁站
        function exportSingle(id, format) {
            window.open(`${API_URL}?action=export_single&id=${id}&format=${format}`, '_blank');
        }

        // 导出所有域名的旁站
        function exportAll(format) {
            const status = document.getElementById('statusFilter').value || 'completed';
            window.open(`${API_URL}?action=export_all&format=${format}&status=${status}`, '_blank');
        }

        // 关闭弹窗
        function closeModal() {
            document.getElementById('sideSiteModal').classList.remove('show');
            currentViewingDomainId = null;
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

        // 点击模态框外部关闭
        document.getElementById('sideSiteModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // ESC键关闭弹窗
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });
    </script>
</body>
</html>
