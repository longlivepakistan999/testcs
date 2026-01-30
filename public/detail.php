<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>旁站详情 - Side Site Detection</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
        }
        header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        header h1 { font-size: 24px; font-weight: 600; }
        header a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border: 1px solid rgba(255,255,255,0.5);
            border-radius: 4px;
        }
        header a:hover { background: rgba(255,255,255,0.1); }
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
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        .info-item label { display: block; font-size: 12px; color: #666; margin-bottom: 5px; }
        .info-item .value { font-size: 18px; font-weight: 600; color: #333; }
        .info-item .value.ip { font-family: monospace; font-size: 14px; }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
        }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-success { background: #28a745; color: white; }
        .action-bar { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; color: #555; position: sticky; top: 0; }
        tr:hover { background: #f8f9fa; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .badge-match { background: #28a745; color: white; }
        .badge-mismatch { background: #dc3545; color: white; }
        .badge-shared { background: #ff6b6b; color: white; }
        .badge-dedicated { background: #4ecdc4; color: white; }
        .badge-cf { background: #f48024; color: white; }
        .text-success { color: #28a745; }
        .text-danger { color: #dc3545; }
        .text-muted { color: #999; }
        .ip-cell { font-family: monospace; font-size: 12px; }
        .loading { text-align: center; padding: 40px; color: #666; }
        .stats-bar {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            padding: 15px;
            background: #e9ecef;
            border-radius: 8px;
            flex-wrap: wrap;
        }
        .stats-bar .stat { display: flex; align-items: center; gap: 5px; }
        .stats-bar .stat-value { font-weight: 600; }
        .table-container { border: 1px solid #eee; border-radius: 8px; }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .pagination button {
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
            border-radius: 4px;
        }
        .pagination button:hover { background: #f8f9fa; }
        .pagination button.active { background: #667eea; color: white; border-color: #667eea; }
        .pagination button:disabled { opacity: 0.5; cursor: not-allowed; }
        .page-info { display: flex; align-items: center; gap: 10px; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1 id="pageTitle">旁站详情</h1>
            <a href="index.php">← 返回列表</a>
        </div>
    </header>

    <div class="container">
        <!-- 域名信息 -->
        <div class="card">
            <h2>域名信息</h2>
            <div class="info-grid" id="domainInfo">
                <div class="loading">加载中...</div>
            </div>
        </div>

        <!-- 旁站列表 -->
        <div class="card">
            <h2>旁站列表</h2>
            <div class="action-bar">
                <select id="exportFilter" style="padding: 10px; border-radius: 6px; border: 1px solid #ddd;">
                    <option value="all">全部旁站</option>
                    <option value="match">仅IP一致</option>
                    <option value="mismatch">仅IP不一致</option>
                </select>
                <button class="btn btn-info" onclick="exportCsv()">导出CSV</button>
                <button class="btn btn-info" onclick="exportJson()">导出JSON</button>
                <button class="btn btn-success" onclick="refreshIp()">刷新IP检测</button>
            </div>
            <div class="stats-bar" id="statsBar" style="display: none;">
                <div class="stat">
                    <span>总数:</span>
                    <span class="stat-value" id="statTotal">0</span>
                </div>
                <div class="stat">
                    <span>当前页:</span>
                    <span class="stat-value" id="statPage">0</span>
                </div>
                <div class="stat">
                    <span>IP一致:</span>
                    <span class="stat-value text-success" id="statMatch">0</span>
                </div>
                <div class="stat">
                    <span>IP不一致:</span>
                    <span class="stat-value text-danger" id="statMismatch">0</span>
                </div>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>旁站域名</th>
                            <th>原始IP</th>
                            <th>当前IP</th>
                            <th>IP状态</th>
                            <th>最后解析</th>
                        </tr>
                    </thead>
                    <tbody id="siteList">
                        <tr><td colspan="6" class="loading">加载中...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination" id="pagination"></div>
        </div>
    </div>

    <script>
        const API_URL = 'api.php';
        const urlParams = new URLSearchParams(window.location.search);
        const domainId = urlParams.get('id');
        const PER_PAGE = 100;

        let currentPage = 1;
        let totalPages = 1;
        let totalCount = 0;

        if (!domainId) {
            alert('缺少域名ID');
            window.location.href = 'index.php';
        }

        // 初始化
        document.addEventListener('DOMContentLoaded', function() {
            loadData(1);
        });

        // 加载数据（从数据库读取，不再实时检测IP）
        async function loadData(page = 1) {
            currentPage = page;
            document.getElementById('siteList').innerHTML = '<tr><td colspan="6" class="loading">加载中...</td></tr>';

            try {
                const response = await fetch(`${API_URL}?action=side_sites&id=${domainId}&page=${page}&per_page=${PER_PAGE}`);
                const result = await response.json();

                if (result.code === 0) {
                    const data = result.data.data || result.data;
                    totalPages = data.total_pages || 1;
                    totalCount = data.total || 0;
                    renderDomainInfo(data.domain);
                    renderSiteList(data.sites, data.original_ip, page, data.match_count, data.mismatch_count);
                    renderPagination();
                } else {
                    alert('加载失败: ' + (result.message || '未知错误'));
                }
            } catch (e) {
                alert('加载失败: ' + e.message);
            }
        }

        // 渲染域名信息
        function renderDomainInfo(domain) {
            if (!domain) {
                document.getElementById('domainInfo').innerHTML = '<p>域名不存在</p>';
                return;
            }

            document.getElementById('pageTitle').textContent = domain.domain + ' - 旁站详情';
            document.title = domain.domain + ' - 旁站详情';

            let hostingBadge = '';
            if (domain.hosting_type === 'shared') {
                hostingBadge = '<span class="badge badge-shared">共享空间</span>';
            } else if (domain.hosting_type === 'dedicated') {
                hostingBadge = '<span class="badge badge-dedicated">独立服务器</span>';
            }

            let cfBadge = domain.is_cloudflare == 1 ? '<span class="badge badge-cf">Cloudflare</span>' : '';

            document.getElementById('domainInfo').innerHTML = `
                <div class="info-item">
                    <label>域名</label>
                    <div class="value">${escapeHtml(domain.domain)}</div>
                </div>
                <div class="info-item">
                    <label>IP地址</label>
                    <div class="value ip">${domain.ip_address || '-'} ${cfBadge}</div>
                </div>
                <div class="info-item">
                    <label>旁站数量</label>
                    <div class="value">${domain.side_site_count || 0}</div>
                </div>
                <div class="info-item">
                    <label>主机类型</label>
                    <div class="value">${hostingBadge || '-'}</div>
                </div>
                <div class="info-item">
                    <label>检测时间</label>
                    <div class="value" style="font-size: 14px;">${domain.detected_at || '-'}</div>
                </div>
            `;
        }

        // 渲染旁站列表
        function renderSiteList(sites, originalIp, page) {
            const tbody = document.getElementById('siteList');

            if (!sites || sites.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="loading">暂无旁站数据</td></tr>';
                document.getElementById('statsBar').style.display = 'none';
                return;
            }

            // 统计（当前页）
            const matchCount = sites.filter(s => s.ip_match).length;
            const mismatchCount = sites.length - matchCount;

            document.getElementById('statTotal').textContent = totalCount;
            document.getElementById('statPage').textContent = sites.length;
            document.getElementById('statMatch').textContent = matchCount;
            document.getElementById('statMismatch').textContent = mismatchCount;
            document.getElementById('statsBar').style.display = 'flex';

            // 计算起始序号
            const startIndex = (page - 1) * PER_PAGE;

            // 渲染列表
            tbody.innerHTML = sites.map((site, index) => {
                const ipMatch = site.ip_match;
                return `<tr>
                    <td>${startIndex + index + 1}</td>
                    <td><a href="http://${escapeHtml(site.side_domain)}" target="_blank">${escapeHtml(site.side_domain)}</a></td>
                    <td class="ip-cell">${site.ip_address || '-'}</td>
                    <td class="ip-cell ${ipMatch ? 'text-success' : 'text-danger'}">${site.current_ip || '-'}</td>
                    <td>${ipMatch ? '<span class="badge badge-match">一致</span>' : '<span class="badge badge-mismatch">不一致</span>'}</td>
                    <td>${site.last_resolved || '-'}</td>
                </tr>`;
            }).join('');
        }

        // 渲染分页
        function renderPagination() {
            const pagination = document.getElementById('pagination');

            if (totalPages <= 1) {
                pagination.innerHTML = '';
                return;
            }

            let html = '';
            html += `<button onclick="loadData(1)" ${currentPage === 1 ? 'disabled' : ''}>首页</button>`;
            html += `<button onclick="loadData(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>上一页</button>`;

            // 页码按钮
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);

            if (startPage > 1) {
                html += `<button onclick="loadData(1)">1</button>`;
                if (startPage > 2) html += `<span style="padding: 8px;">...</span>`;
            }

            for (let i = startPage; i <= endPage; i++) {
                html += `<button onclick="loadData(${i})" class="${i === currentPage ? 'active' : ''}">${i}</button>`;
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) html += `<span style="padding: 8px;">...</span>`;
                html += `<button onclick="loadData(${totalPages})">${totalPages}</button>`;
            }

            html += `<button onclick="loadData(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>下一页</button>`;
            html += `<button onclick="loadData(${totalPages})" ${currentPage === totalPages ? 'disabled' : ''}>末页</button>`;

            html += `<span class="page-info" style="margin-left: 20px;">共 ${totalCount} 条，${totalPages} 页</span>`;

            pagination.innerHTML = html;
        }

        // 导出CSV
        function exportCsv() {
            const filter = document.getElementById('exportFilter').value;
            window.open(`${API_URL}?action=export_single&id=${domainId}&format=csv&ip_filter=${filter}`, '_blank');
        }

        // 导出JSON
        function exportJson() {
            const filter = document.getElementById('exportFilter').value;
            window.open(`${API_URL}?action=export_single&id=${domainId}&format=json&ip_filter=${filter}`, '_blank');
        }

        // 刷新IP检测（重新检测所有旁站的当前IP并更新数据库）
        async function refreshIp() {
            if (!confirm('确定要重新检测所有旁站的IP吗？这可能需要一些时间。')) {
                return;
            }

            const btn = event.target;
            btn.disabled = true;
            btn.textContent = '检测中...';

            try {
                const response = await fetch(`${API_URL}?action=refresh_ip`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: parseInt(domainId) })
                });

                const result = await response.json();

                if (result.code === 0) {
                    alert(result.data.message || '刷新完成');
                    loadData(currentPage);
                } else {
                    alert('刷新失败: ' + (result.message || '未知错误'));
                }
            } catch (e) {
                alert('刷新失败: ' + e.message);
            } finally {
                btn.disabled = false;
                btn.textContent = '刷新IP检测';
            }
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
