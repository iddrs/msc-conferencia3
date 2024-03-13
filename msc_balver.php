<?php

// Configurações

// Pega dados do usuário
echo "Conferência da MSC.", PHP_EOL;
echo "Digite os dados solicitados.", PHP_EOL;

echo "Ano [AAAA]: ";
fscanf(STDIN, "%d\n", $ano);
echo "Mês [MM] (1 ~ 13): ";
fscanf(STDIN, "%d\n", $mes);
$mes = str_pad($mes, 2, '0', STR_PAD_LEFT);

$remessa = (int) $ano.$mes;

// Prepara o ambiente
require 'vendor/autoload.php';

$con = connect('host=localhost port=5432 dbname=pmidd user=postgres password=lise890');
echo 'Conectado ao banco de dados'.PHP_EOL;


echo 'Executando testes...'.PHP_EOL;

//echo "\t-> Saldo final anterior x saldo inicial atual", PHP_EOL;
//$testSaldoAnteriorAtual = new \App\Test\SaldoAnteriorAtualTest($con, $remessa);
//$testSaldoAnteriorAtual->run();
//
//echo "\t-> Saldo final x saldo final calculado", PHP_EOL;
//$testSaldoFinalCalculado = new \App\Test\SaldoFinalCalculadoTest($con, $remessa);
//$testSaldoFinalCalculado->run();

echo "\t-> Saldos/Movimentação: PAD x MSC", PHP_EOL;
$testMscBalVer = new \App\Test\MscBalVerTest($con, $remessa);
$testMscBalVer->run();
