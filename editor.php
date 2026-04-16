<?php
$dataFile = __DIR__ . '/data/filmes.json';

// Lógica de Salvamento (API interna)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input) {
        file_put_contents($dataFile, json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }
}

// Carregar dados iniciais
$db = file_exists($dataFile) ? file_get_contents($dataFile) : '{}';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Stremio Admin - Local</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>body { background: #0f172a; color: #f1f5f9; }</style>
</head>
<body class="p-4 md:p-8">
    <div class="max-w-5xl mx-auto">
        <header class="flex justify-between items-center mb-8 bg-slate-800 p-6 rounded-2xl border border-slate-700">
            <div>
                <h1 class="text-2xl font-bold">🛠️ Addon Manager</h1>
                <p class="text-slate-400 text-sm">Editando diretamente: <span class="text-blue-400">data/filmes.json</span></p>
            </div>
            <div class="flex gap-3">
                <button onclick="saveToServer()" id="btnSave" class="bg-green-600 hover:bg-green-700 px-6 py-2 rounded-lg font-bold transition flex items-center gap-2">
                    <i class="fas fa-cloud-upload-alt"></i> Salvar no Arquivo
                </button>
            </div>
        </header>

        <div class="mb-6 flex gap-3">
            <button onclick="addCategory()" class="bg-slate-700 hover:bg-slate-600 px-4 py-2 rounded-lg border border-slate-600 transition">
                <i class="fas fa-folder-plus mr-2"></i>Nova Categoria
            </button>
        </div>

        <div id="categoriesContainer" class="space-y-6"></div>
    </div>

    <div id="searchModal" class="fixed inset-0 bg-black/80 hidden items-center justify-center p-4 z-50">
        <div class="bg-slate-800 border border-slate-700 w-full max-w-lg rounded-2xl p-6">
            <input type="text" id="searchInput" oninput="searchMovies(this.value)" placeholder="Pesquisar filme..." class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 mb-4 outline-none text-white font-bold">
            <div id="searchResults" class="max-h-60 overflow-y-auto space-y-2"></div>
            <button onclick="closeSearch()" class="mt-4 w-full py-2 text-slate-400">Fechar</button>
        </div>
    </div>

    <script>
        let db = <?php echo $db; ?>;
        let currentAddingCat = '';

        async function saveToServer() {
            const btn = document.getElementById('btnSave');
            btn.disabled = true;
            btn.innerHTML = "Salvando...";
            
            try {
                const response = await fetch('editor.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(db)
                });
                if (response.ok) {
                    btn.className = "bg-blue-600 px-6 py-2 rounded-lg font-bold";
                    btn.innerHTML = "✅ Salvo!";
                    setTimeout(() => {
                        btn.className = "bg-green-600 hover:bg-green-700 px-6 py-2 rounded-lg font-bold transition";
                        btn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Salvar no Arquivo';
                        btn.disabled = false;
                    }, 2000);
                }
            } catch (e) { alert("Erro ao salvar!"); btn.disabled = false; }
        }

        function render() {
            const container = document.getElementById('categoriesContainer');
            container.innerHTML = '';
            for (const [catId, films] of Object.entries(db)) {
                const catDiv = document.createElement('div');
                catDiv.className = "bg-slate-800 rounded-xl border border-slate-700 overflow-hidden shadow-lg";
                catDiv.innerHTML = `
                    <div class="bg-slate-700/50 p-4 flex justify-between items-center border-b border-slate-700">
                        <input type="text" value="${catId}" onchange="renameCategory('${catId}', this.value)" class="bg-slate-900 border border-slate-600 rounded px-2 py-1 text-blue-300 font-mono text-sm focus:ring-1 focus:ring-blue-500 outline-none">
                        <button onclick="deleteCategory('${catId}')" class="text-red-400 hover:text-red-300 p-2"><i class="fas fa-trash"></i></button>
                    </div>
                    <div class="p-4">
                        <table class="w-full text-sm">
                            <tbody id="list-${catId}">
                                ${films.map((f, index) => `
                                    <tr class="border-b border-slate-700/50 hover:bg-slate-700/20">
                                        <td class="py-2 pr-4"><input type="text" value="${f.name}" onchange="editFilm('${catId}', ${index}, 'name', this.value)" class="bg-transparent w-full outline-none focus:text-blue-200"></td>
                                        <td class="py-2 font-mono text-orange-400 text-xs w-32"><input type="text" value="${f.id}" onchange="editFilm('${catId}', ${index}, 'id', this.value)" class="bg-transparent w-full outline-none"></td>
                                        <td class="text-right"><button onclick="deleteFilm('${catId}', ${index})" class="text-slate-500 hover:text-red-400"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                        <div class="mt-4 flex gap-4 border-t border-slate-700 pt-4">
                            <button onclick="openSearch('${catId}')" class="text-blue-400 hover:text-blue-300 text-sm font-bold flex items-center gap-1">
                                <i class="fas fa-search"></i> Pesquisar
                            </button>
                            <button onclick="addFilmManual('${catId}')" class="text-slate-400 hover:text-slate-300 text-sm font-bold flex items-center gap-1">
                                <i class="fas fa-keyboard"></i> Manual
                            </button>
                        </div>
                    </div>`;
                container.appendChild(catDiv);
            }
        }

        function renameCategory(oldId, newId) { if(newId && oldId !== newId) { db[newId] = db[oldId]; delete db[oldId]; render(); } }
        function deleteCategory(catId) { if(confirm("Deseja excluir toda a categoria?")) { delete db[catId]; render(); } }
        function editFilm(catId, i, field, val) { db[catId][i][field] = val; }
        function deleteFilm(catId, i) { db[catId].splice(i, 1); render(); }
        function addFilmManual(catId) { db[catId].push({id: "tt", name: "Novo Filme"}); render(); }
        function addCategory() { const n = prompt("ID da categoria (ex: acao_2024):"); if(n) { db[n] = []; render(); } }
        
        function openSearch(c) { 
            currentAddingCat = c; 
            document.getElementById('searchModal').classList.replace('hidden', 'flex'); 
            document.getElementById('searchInput').focus();
        }
        
        function closeSearch() { 
            document.getElementById('searchModal').classList.replace('flex', 'hidden'); 
            document.getElementById('searchResults').innerHTML = '';
            document.getElementById('searchInput').value = '';
        }

        async function searchMovies(q) {
            if(q.length < 3) return;
            const res = await fetch(`https://v3-cinemeta.strem.io/catalog/movie/top/search=${encodeURIComponent(q)}.json`);
            const data = await res.json();
            
            document.getElementById('searchResults').innerHTML = data.metas.map(m => {
                // Captura o ano se disponível, ou tenta extrair da data de lançamento
                const year = m.year ? m.year : (m.releaseInfo ? m.releaseInfo.substring(0, 4) : 'N/A');
                
                return `
                    <div onclick="selectMovie('${m.id}', '${m.name.replace(/'/g, "")} ')" class="p-2 hover:bg-slate-700 cursor-pointer flex gap-3 items-center rounded transition">
                        <img src="${m.poster}" class="w-8 h-12 object-cover rounded">
                        <div>
                            <div class="font-bold text-sm text-white">${m.name} <span class="text-blue-400">(${year})</span></div>
                            <div class="text-xs text-slate-400 font-mono">${m.id}</div>
                        </div>
                    </div>`;
            }).join('');
        }

        function selectMovie(id, name) { 
            db[currentAddingCat].push({id, name}); 
            render(); 
            closeSearch(); 
        }
        
        render();
    </script>
</body>
</html>