<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../conexao.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

$app->add(function (Request $request, RequestHandler $handler): Response {
    $resp = $handler->handle($request);
    return $resp->withHeader('Content-Type', 'application/json; charset=utf-8');
});

function pegarPTAX($moeda){
    $moeda = strtoupper($moeda);
    $hoje = date('m-d-Y');
    $dataInicial = date('m-d-Y', strtotime('-7 days'));
    $url = "https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata/CotacaoMoedaPeriodoFechamento(codigoMoeda=@codigoMoeda,dataInicialCotacao=@dataInicialCotacao,dataFinalCotacao=@dataFinalCotacao)?@codigoMoeda='{$moeda}'&@dataInicialCotacao='{$dataInicial}'&@dataFinalCotacao='{$hoje}'&\$top=1&\$format=json&\$orderby=dataHoraCotacao%20desc&\$select=cotacaoCompra,cotacaoVenda,dataHoraCotacao,tipoBoletim";
    $dados = json_decode(file_get_contents($url), true);
    if (isset($dados['value'][0])) {
        return [
            'compra' => $dados['value'][0]['cotacaoCompra'],
            'venda'  => $dados['value'][0]['cotacaoVenda']
        ];
    }
    return ['compra' => 1, 'venda' => 1];
}

// DEPÓSITO
$app->post('/deposito', function(Request $requisicao, Response $resposta) use($pdo){
    $dados = $requisicao->getParsedBody();
    $numeroConta = $dados['numero_conta'] ?? null;
    $valorDeposito = isset($dados['valor']) ? (float)$dados['valor'] : 0;
    $moeda = isset($dados['moeda']) ? strtoupper($dados['moeda']) : null;

    if(!$numeroConta || $valorDeposito <= 0 || !$moeda){
        $resposta->getBody()->write(json_encode(['erro' => 'Parâmetros inválidos']));
        return $resposta->withStatus(400);
    }

    try{
        $pdo->beginTransaction();
        $consulta = $pdo->prepare("SELECT id, saldo FROM contas WHERE numero_conta=? AND moeda=? FOR UPDATE");
        $consulta->execute([$numeroConta, $moeda]);
        $conta = $consulta->fetch();

        if($conta){
            $novoSaldo = $conta['saldo'] + $valorDeposito;
            $pdo->prepare("UPDATE contas SET saldo=? WHERE id=?")->execute([$novoSaldo, $conta['id']]);
        }else{
            $pdo->prepare("INSERT INTO contas(numero_conta, moeda, saldo) VALUES(?, ?, ?)")->execute([$numeroConta, $moeda, $valorDeposito]);
        }

        $pdo->commit();
        $resposta->getBody()->write(json_encode(['mensagem'=>'Depósito realizado com sucesso','valor'=>$valorDeposito,'moeda'=>$moeda]));
        return $resposta->withStatus(201);
    }catch(Exception $erro){
        $pdo->rollBack();
        $resposta->getBody()->write(json_encode(['erro'=>'Erro ao realizar depósito','detalhe'=>$erro->getMessage()]));
        return $resposta->withStatus(500);
    }
});

// SAQUE
$app->post('/saque', function(Request $requisicao, Response $resposta) use($pdo){
    $dados = $requisicao->getParsedBody();
    $numeroConta = $dados['numero_conta'] ?? null;
    $valorSaque = isset($dados['valor']) ? (float)$dados['valor'] : 0;
    $moedaSaque = isset($dados['moeda']) ? strtoupper($dados['moeda']) : null;

    if(!$numeroConta || $valorSaque <= 0 || !$moedaSaque){
        $resposta->getBody()->write(json_encode(['erro'=>'Parâmetros inválidos']));
        return $resposta->withStatus(400);
    }

    try{
        $pdo->beginTransaction();
        $consulta = $pdo->prepare("SELECT id, moeda, saldo FROM contas WHERE numero_conta=? FOR UPDATE");
        $consulta->execute([$numeroConta]);
        $todasContas = $consulta->fetchAll();

        $saldoTotalConvertido = 0;
        foreach($todasContas as $conta){
            if($conta['moeda'] == $moedaSaque){
                $saldoTotalConvertido += $conta['saldo'];
            } else {
                $cotacao = pegarPTAX($conta['moeda']);
                if($conta['moeda'] == 'BRL'){
                    $saldoTotalConvertido += $conta['saldo'] / pegarPTAX($moedaSaque)['venda'];
                } else {
                    $saldoTotalConvertido += ($conta['saldo'] * $cotacao['compra']) / pegarPTAX($moedaSaque)['venda'];
                }
            }
        }

        if($saldoTotalConvertido < $valorSaque){
            $pdo->rollBack();
            $resposta->getBody()->write(json_encode(['erro'=>'Saldo insuficiente mesmo convertendo']));
            return $resposta->withStatus(400);
        }

        foreach($todasContas as $conta){
            if($conta['moeda'] == $moedaSaque && $valorSaque > 0){
                $retirada = min($conta['saldo'], $valorSaque);
                $novoSaldo = $conta['saldo'] - $retirada;
                $pdo->prepare("UPDATE contas SET saldo=? WHERE id=?")->execute([$novoSaldo, $conta['id']]);
                $valorSaque -= $retirada;
            }
        }

        $pdo->commit();
        $resposta->getBody()->write(json_encode(['mensagem'=>'Saque realizado com sucesso','moeda'=>$moedaSaque]));
        return $resposta->withStatus(200);

    }catch(Exception $erro){
        $pdo->rollBack();
        $resposta->getBody()->write(json_encode(['erro'=>'Erro ao realizar saque','detalhe'=>$erro->getMessage()]));
        return $resposta->withStatus(500);
    }
});

// SALDO
$app->get('/saldo/{numero_conta}[/{moeda}]', function(Request $requisicao, Response $resposta, $args) use($pdo){
    $numeroConta = $args['numero_conta'] ?? null;
    $moedaDesejada = isset($args['moeda']) ? strtoupper($args['moeda']) : null;

    if(!$numeroConta){
        $resposta->getBody()->write(json_encode(['erro'=>'Número da conta necessário']));
        return $resposta->withStatus(400);
    }

    $consulta = $pdo->prepare("SELECT moeda, saldo FROM contas WHERE numero_conta=?");
    $consulta->execute([$numeroConta]);
    $contas = $consulta->fetchAll();

    if(empty($contas)){
        $resposta->getBody()->write(json_encode(['erro'=>'Conta não encontrada']));
        return $resposta->withStatus(404);
    }

    $resultado = [];
    if($moedaDesejada){
        $saldoTotalConvertido = 0;
        foreach($contas as $conta){
            if($conta['moeda'] == $moedaDesejada){
                $saldoTotalConvertido += $conta['saldo'];
            } else {
                $cotacao = pegarPTAX($conta['moeda']);
                if($conta['moeda'] == 'BRL'){
                    $saldoTotalConvertido += $conta['saldo'] / pegarPTAX($moedaDesejada)['venda'];
                } else {
                    $saldoTotalConvertido += ($conta['saldo'] * $cotacao['compra']) / pegarPTAX($moedaDesejada)['venda'];
                }
            }
        }
        $resultado = ['saldo_total' => $saldoTotalConvertido, 'moeda' => $moedaDesejada];
    } else {
        foreach($contas as $conta){
            $resultado[$conta['moeda']] = $conta['saldo'];
        }
    }

    $resposta->getBody()->write(json_encode($resultado));
    return $resposta->withStatus(200);
});

$app->get('/', function(Request $requisicao, Response $resposta){
    $resposta->getBody()->write(json_encode(['mensagem'=>'API Banco funcionando']));
    return $resposta;
});

$app->run();