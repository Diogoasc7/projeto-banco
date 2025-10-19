# Projeto Banco API

## Descrição
API em PHP para gerenciamento de contas bancárias.  
Permite realizar operações de **depósito**, **saque** e consulta de **saldo**, incluindo conversão de moedas utilizando a **API PTAX**.

O projeto utiliza:
- Framework **Slim** para criar a API
- Banco de dados **MySQL** via XAMPP
- Servidor local com PHP embutido ou Apache


## Pré-requisitos
Antes de executar a aplicação, certifique-se de ter instalado:

- PHP >= 8.0
- Composer
- MySQL (via XAMPP)
- Extensões PHP: PDO, cURL
- Navegador ou Postman para testar a API


## Instalação
1. **Clonar o repositório**
git clone https://github.com/Diogoasc7/projeto-banco/tree/master

2. **Acessar a pasta do projeto**
cd projeto-banco

3. **Instalar dependências via Composer**
composer install

4. **Configurar banco de dados**
Crie um banco no MySQL
Crie a tabela contas:
CREATE TABLE contas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_conta INT NOT NULL,
    saldo DECIMAL(15,2) NOT NULL,
    moeda VARCHAR(3) NOT NULL
);
--Atualize as credenciais de conexão em conexao.php:
$pdo = new PDO('mysql:host=localhost;dbname=banco_api', 'usuario', 'senha')


## Execução da aplicação
1. **Inicie o servidor XAMPP (Apache e MySQL)**

2. Rode a aplicação com o servidor embutido do PHP (ou configure o Apache para apontar para a pasta public/):
php -S localhost:8000 -t public

3. **Acesse a API pelo navegador ou Postman:**
http://localhost:8000/saldo/{numero_conta}
http://localhost:8000/saldo/{numero_conta}/{moeda}


## Endpoints Disponiveis
1. Método - GET -> Endpoint - /saldo/{numero_conta}[/{moeda}] -> Descrição - Consulta saldo de uma conta -> Parâmetros - numero_conta obrigatório, moeda opcional.

2. Método - POST -> Endpoint - /deposito -> Descrição - Deposita valor em uma conta -> Parâmetros - JSON: { "numero_conta": 123, "valor": 100.0, "moeda": "BRL" }

3. Método - POST -> Endpoint - /saque -> Descrição - Realiza saque de uma conta -> Parâmetros - JSON: { "numero_conta": 123, "valor": 50.0, "moeda": "BRL" }

**Observações:**
Se não passar a moeda no endpoint de saldo, retorna o saldo em todas as moedas disponíveis.
Se passar a moeda, o saldo total é convertido para a moeda solicitada usando a API PTAX.


## Estrutura do projeto
projeto-banco/ -> 
public/         # Pasta pública para o servidor ->
index.php       # Arquivo principal da API ->
vendor/         # Dependências do Composer ->
composer.json   # Dependências e autoload ->
composer.lock   # Fixa as versões das dependências do projeto. ->
conexao.php     # Configuração do banco de dados ->
README.md       # Este arquivo


## API PTAX
A API é utilizada para obter cotação de moedas e permitir conversão de saldo entre diferentes moedas.
Endpoint usado:
https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata/


## Contato
Desenvolvedor: Diogo Augusto Costa Soares -> E-mail: diogodsc1456@gmail.com