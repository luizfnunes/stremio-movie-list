# Usando a versão CLI do PHP no Alpine (super leve)
FROM php:8.2-cli-alpine

# Define o diretório de trabalho
WORKDIR /app

# Copia os arquivos locais para dentro do container
# (O docker-compose vai sobrescrever isso com o volume, mas é boa prática)
COPY . .

# Comando para rodar o build
CMD ["php", "build.php"]