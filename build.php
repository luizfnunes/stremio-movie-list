<?php
// Garante que os arquivos criados pelo Docker tenham permissões de leitura/escrita
umask(0000);

// Configurações Iniciais
$publicDir = __DIR__ . '/public';
$dataFile = __DIR__ . '/data/filmes.json';

// 1. Carregar os dados do JSON
if (!file_exists($dataFile)) {
    die("❌ Erro: O arquivo data/filmes.json não foi encontrado!\n");
}

$filmesPorCategoria = json_decode(file_get_contents($dataFile), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("❌ Erro: O seu filmes.json está com formato inválido!\n");
}

// 2. Preparar os Catálogos para o Manifesto
$catalogs = [];
foreach (array_keys($filmesPorCategoria) as $catId) {
    $catalogs[] = [
        "type" => "movie",
        "id" => $catId,
        "name" => ucwords(str_replace(['_', '-'], ' ', $catId)) 
    ];
}

// 3. Estrutura do Manifesto
$manifest = [
    "id" => "org.meuaddon.pessoal",
    "version" => "1.0.0",
    "name" => "Meu Catálogo Pessoal",
    "description" => "Lista personalizada de filmes organizada por categorias",
    "resources" => ["catalog", "meta"],
    "types" => ["movie"],
    "idPrefixes" => ["tt"],
    "catalogs" => $catalogs
];

// 4. Limpeza e Criação de Pastas
if (is_dir($publicDir)) {
    // Comando compatível com Linux/Docker para limpar a pasta
    system("rm -rf " . escapeshellarg($publicDir));
}
mkdir($publicDir . '/catalog/movie', 0777, true);
mkdir($publicDir . '/meta/movie', 0777, true);

// 5. Salvar o Manifesto
file_put_contents($publicDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// 6. Gerar os arquivos de Catálogo e Metadados
echo "🚀 Iniciando build do addon...\n";

foreach ($filmesPorCategoria as $catId => $filmes) {
    $metasDoCatalogo = [];

    foreach ($filmes as $item) {
        $id = $item['id'];
        $nome = $item['name'];

        // Estrutura do Meta Individual
        $metaData = [
            "meta" => [
                "id" => $id,
                "type" => "movie",
                "name" => $nome,
                "poster" => "https://images.metahub.space/poster/medium/$id/img",
                "background" => "https://images.metahub.space/background/medium/$id/img"
            ]
        ];

        // Salva o arquivo individual na pasta meta (ex: meta/movie/tt0111161.json)
        file_put_contents("$publicDir/meta/movie/$id.json", json_encode($metaData, JSON_PRETTY_PRINT));

        // Adiciona à lista deste catálogo específico
        $metasDoCatalogo[] = $metaData['meta'];
    }

    // Salva o arquivo de catálogo da categoria (ex: catalog/movie/categoria_favoritos.json)
    $catalogPath = "$publicDir/catalog/movie/$catId.json";
    file_put_contents($catalogPath, json_encode(["metas" => $metasDoCatalogo], JSON_PRETTY_PRINT));
    
    echo "  - Categoria '$catId' gerada com " . count($filmes) . " filmes.\n";
}

echo "✅ Build concluído com sucesso na pasta /public!\n";

// 7. Gerar Página de Instalação (index.html)
$addonName = $manifest['name'];
$installUrl = "stremio://" . $_SERVER['HTTP_HOST'] ?? 'seu-usuario.github.io/seu-repo' . "/manifest.json";
// Nota: Como o script roda via CLI, vamos usar um placeholder ou variável para a URL
$urlFinal = "https://luizfnunes.github.io/stremio-movie-list"; // ALTERE PARA SUA URL REAL

$htmlContent = <<<HTML
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$addonName - Stremio Addon</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #0d0d12; color: white; font-family: sans-serif; }
        .stremio-purple { background: #8152bf; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-6">
    <div class="max-w-md w-full text-center bg-gray-900 p-8 rounded-2xl shadow-2xl border border-gray-800">
        <img src="https://www.stremio.com/website/stremio-logo-small.png" alt="Stremio" class="w-16 mx-auto mb-4">
        <h1 class="text-3xl font-bold mb-2">$addonName</h1>
        <p class="text-gray-400 mb-6">Instale este catálogo personalizado diretamente no seu Stremio.</p>
        
        <div class="space-y-4">
            <a href="stremio://$urlFinal/manifest.json" 
               class="block w-full stremio-purple hover:bg-purple-600 text-white font-bold py-3 px-6 rounded-lg transition-all transform hover:scale-105">
                + INSTALAR ADDON
            </a>
            
            <div class="text-sm text-gray-500 pt-4 border-t border-gray-800">
                <p class="mb-2">Não funcionou? Copie o link abaixo e cole na busca de addons do Stremio:</p>
                <code class="block bg-black p-2 rounded text-purple-400 break-all">https://$urlFinal/manifest.json</code>
            </div>
        </div>
    </div>
</body>
</html>
HTML;

file_put_contents($publicDir . '/index.html', $htmlContent);