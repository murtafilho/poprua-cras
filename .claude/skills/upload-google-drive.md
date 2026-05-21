---
name: upload-google-drive
description: Upload de arquivos para pasta específica do Google Drive. Use para enviar arquivos via API do Google Drive com autenticação OAuth2.
allowed-tools: Bash, Read, Write, Edit
---

# Upload Google Drive

Skill para upload de arquivos para uma pasta específica do Google Drive (murtafilho@gmail.com).

---

## SETUP RÁPIDO

### 1. Credenciais OAuth2

Criar projeto no [Google Cloud Console](https://console.cloud.google.com/):
1. APIs & Services > Enable APIs > Google Drive API
2. Credentials > Create OAuth Client ID > Desktop App
3. Salvar `client_id` e `client_secret` no `.env`:

```env
GOOGLE_DRIVE_CLIENT_ID=seu_client_id
GOOGLE_DRIVE_CLIENT_SECRET=seu_client_secret
GOOGLE_DRIVE_REDIRECT_URI=http://localhost:8080/callback
GOOGLE_DRIVE_FOLDER_ID=id_da_pasta_destino
```

### 2. Obter o Folder ID

Na URL do Google Drive, o ID da pasta é a parte final:
```
https://drive.google.com/drive/folders/1aBcDeFgHiJkLmNoPqRsTuVwXyZ
                                       └─── FOLDER_ID ───┘
```

### 3. Pacote PHP

```bash
composer require google/apiclient
```

---

## AUTENTICAÇÃO

### Gerar Token (primeira vez)

```php
use Google\Client;
use Google\Service\Drive;

$client = new Client();
$client->setClientId(env('GOOGLE_DRIVE_CLIENT_ID'));
$client->setClientSecret(env('GOOGLE_DRIVE_CLIENT_SECRET'));
$client->setRedirectUri(env('GOOGLE_DRIVE_REDIRECT_URI'));
$client->setScopes([Drive::DRIVE_FILE]);
$client->setAccessType('offline');
$client->setPrompt('consent');

// Passo 1: Gerar URL de autorização
$authUrl = $client->createAuthUrl();
// Abrir no navegador, autorizar, copiar o código

// Passo 2: Trocar código por token
$token = $client->fetchAccessTokenWithAuthCode($codigo);
file_put_contents(storage_path('app/google_drive_token.json'), json_encode($token));
```

### Carregar Token Existente

```php
$tokenPath = storage_path('app/google_drive_token.json');
$token = json_decode(file_get_contents($tokenPath), true);
$client->setAccessToken($token);

// Renovar se expirado
if ($client->isAccessTokenExpired() && $client->getRefreshToken()) {
    $newToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
    file_put_contents($tokenPath, json_encode($newToken));
}
```

---

## UPLOAD PARA PASTA ESPECÍFICA

### Upload simples

```php
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

$drive = new Drive($client);
$folderId = env('GOOGLE_DRIVE_FOLDER_ID');

$fileMetadata = new DriveFile([
    'name' => 'meu-arquivo.pdf',
    'parents' => [$folderId],
]);

$file = $drive->files->create($fileMetadata, [
    'data' => file_get_contents('/caminho/do/arquivo.pdf'),
    'mimeType' => 'application/pdf',
    'uploadType' => 'multipart',
    'fields' => 'id, name, webViewLink',
]);

echo $file->getId();       // ID do arquivo no Drive
echo $file->getWebViewLink(); // Link para visualizar
```

### Upload com conversão para Google Docs

```php
// HTML -> Google Document
$fileMetadata = new DriveFile([
    'name' => 'Meu Documento',
    'parents' => [$folderId],
    'mimeType' => 'application/vnd.google-apps.document',
]);

$file = $drive->files->create($fileMetadata, [
    'data' => file_get_contents('documento.html'),
    'mimeType' => 'text/html',
    'uploadType' => 'multipart',
    'fields' => 'id, name, webViewLink',
]);
```

### Upload de imagem

```php
$fileMetadata = new DriveFile([
    'name' => 'foto.jpg',
    'parents' => [$folderId],
]);

$file = $drive->files->create($fileMetadata, [
    'data' => file_get_contents('foto.jpg'),
    'mimeType' => 'image/jpeg',
    'uploadType' => 'multipart',
    'fields' => 'id, name, webViewLink',
]);
```

---

## MIME TYPES COMUNS

| Extensão | MIME Type | Conversão Google |
|----------|----------|------------------|
| .pdf | `application/pdf` | - |
| .jpg/.jpeg | `image/jpeg` | - |
| .png | `image/png` | - |
| .html | `text/html` | `application/vnd.google-apps.document` |
| .csv | `text/csv` | `application/vnd.google-apps.spreadsheet` |
| .xlsx | `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` | `application/vnd.google-apps.spreadsheet` |
| .docx | `application/vnd.openxmlformats-officedocument.wordprocessingml.document` | `application/vnd.google-apps.document` |

---

## OPERAÇÕES ÚTEIS

### Listar arquivos da pasta

```php
$results = $drive->files->listFiles([
    'q' => "'{$folderId}' in parents and trashed = false",
    'fields' => 'files(id, name, mimeType, createdTime)',
    'orderBy' => 'createdTime desc',
    'pageSize' => 20,
]);

foreach ($results->getFiles() as $file) {
    echo "{$file->getName()} ({$file->getId()})\n";
}
```

### Verificar se arquivo já existe na pasta

```php
$nome = 'meu-arquivo.pdf';
$results = $drive->files->listFiles([
    'q' => "name = '{$nome}' and '{$folderId}' in parents and trashed = false",
    'fields' => 'files(id, name)',
]);

if (count($results->getFiles()) > 0) {
    echo "Arquivo já existe: " . $results->getFiles()[0]->getId();
}
```

### Substituir arquivo existente (update)

```php
$fileId = 'id_do_arquivo_existente';

$file = $drive->files->update($fileId, new DriveFile([
    'name' => 'nome-atualizado.pdf',
]), [
    'data' => file_get_contents('novo-arquivo.pdf'),
    'mimeType' => 'application/pdf',
    'uploadType' => 'multipart',
    'fields' => 'id, name, webViewLink',
]);
```

### Criar subpasta

```php
$subpasta = $drive->files->create(new DriveFile([
    'name' => 'Minha Subpasta',
    'mimeType' => 'application/vnd.google-apps.folder',
    'parents' => [$folderId],
]), ['fields' => 'id, name']);

echo $subpasta->getId(); // Usar como parent para uploads
```

### Deletar arquivo

```php
$drive->files->delete($fileId);
```

---

## TROUBLESHOOTING

| Problema | Solução |
|----------|---------|
| `Token expirado` | Renovar com `fetchAccessTokenWithRefreshToken()` |
| `File not found` | Verificar se `FOLDER_ID` está correto e acessível |
| `Insufficient permissions` | Usar scope `Drive::DRIVE_FILE` ou `Drive::DRIVE` |
| `SSL certificate problem` | Adicionar `new \GuzzleHttp\Client(['verify' => false])` via `$client->setHttpClient()` |
| `Rate limit exceeded` | Adicionar delay entre uploads em lote |
