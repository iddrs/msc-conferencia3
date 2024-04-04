<?php

/**
 * Testa se os saldos e moviimentações da MSC são iguais aos do balancete contábil.
 * 
 * Considera as contas contábeis.
 */

namespace App\Test;

class MscBalVerTest extends BaseTest {
    
    public function __construct(\PgSql\Connection $con, int $remessa) {
        $file_name = explode('\\', __CLASS__);
        $report = './report/'.array_pop($file_name).'.csv';
        parent::__construct($con, $remessa, $report);
    }
       
    public function run(): void {
        $this->query('DELETE FROM tmp.msc_balver');
        $this->montaBalVerMensal();
        
        $this->salvaResultado();
        
        $header = [
            'conta_contabil',
            'pad_saldo_inicial',
            'msc_saldo_inicial',
            'pad_movimento_debito',
            'msc_movimento_debito',
            'pad_movimento_credito',
            'msc_movimento_credito',
            'pad_saldo_final',
            'msc_saldo_final',
            'diferenca_saldo_inicial',
            'diferenca_movimento_debito',
            'diferenca_movimento_credito',
            'diferenca_saldo_final'
        ];
        $this->salvaRelatorio($header);
    }
    
    private function montaBalVerMensal(): void {
        echo "Montando o balancete contábil mensal...", PHP_EOL;
        if($this->mes === 13){
            $this->montaBalVerEncerramento();
        }else{
            $this->montaBalVerNormal();
        }
    }
    
    private function montaBalVerNormal(): void {
        $tabela = 'pad.bal_ver';
        $this->apagaBalVerMensal();
        $this->calculaSaldoInicialBalVer($tabela);
        $this->calculaMovimentoDebitoBalVer();
        $this->calculaMovimentoCreditoBalVer();
        $this->calculaSaldoFinalBalVer($tabela);
        $this->buscaMapeamentos();
    }
    
    private function calculaDiferencas(array $cc): void {
        echo "\t-> Calculando as diferenças PAD / MSC...", PHP_EOL;
        $pb = new \NickBeen\ProgressBar\ProgressBar(maxProgress: sizeof($cc));
        begin_transaction($this->con);
        foreach ($pb->iterate($cc) as $i => $conta_contabil){
            $data['conta_contabil'] = $conta_contabil;
            
            $result = $this->query(sprintf("SELECT SUM(saldo_inicial)::decimal AS valor FROM tmp.balver_mensal WHERE conta_contabil_msc LIKE '%s'", $conta_contabil));
            $pad_saldo_inicial= round(array_sum(pg_fetch_all_columns($result, 0)), 2);
            $data['pad_saldo_inicial'] = $pad_saldo_inicial;
            
            $result = $this->query(sprintf("SELECT SUM(movimento_debito)::decimal AS valor FROM tmp.balver_mensal WHERE conta_contabil_msc LIKE '%s'", $conta_contabil));
            $pad_movimento_debito = round(array_sum(pg_fetch_all_columns($result, 0)), 2);
            $data['pad_movimento_debito'] = $pad_movimento_debito;
            
            $result = $this->query(sprintf("SELECT SUM(movimento_credito)::decimal AS valor FROM tmp.balver_mensal WHERE conta_contabil_msc LIKE '%s'", $conta_contabil));
            $pad_movimento_credito = round(array_sum(pg_fetch_all_columns($result, 0)), 2);
            $data['pad_movimento_credito'] = $pad_movimento_credito;
            
            $result = $this->query(sprintf("SELECT SUM(saldo_final)::decimal AS valor FROM tmp.balver_mensal WHERE conta_contabil_msc LIKE '%s'", $conta_contabil));
            $pad_saldo_final = round(array_sum(pg_fetch_all_columns($result, 0)), 2);
            $data['pad_saldo_final'] = $pad_saldo_final;
            
            
            $result = $this->query(sprintf("SELECT SUM(saldo_inicial)::decimal AS valor FROM msc.msc WHERE conta_contabil LIKE '%s' AND remessa = %d", $conta_contabil, $this->remessa));
            $msc_saldo_inicial = round(array_sum(pg_fetch_all_columns($result, 0)), 2);
            $data['msc_saldo_inicial'] = $msc_saldo_inicial;
            
            $result = $this->query(sprintf("SELECT SUM(movimento_debito)::decimal AS valor FROM msc.msc WHERE conta_contabil LIKE '%s' AND remessa = %d", $conta_contabil, $this->remessa));
            $msc_movimento_debito = round(array_sum(pg_fetch_all_columns($result, 0)), 2);
            $data['msc_movimento_debito'] = $msc_movimento_debito;
            
            $result = $this->query(sprintf("SELECT SUM(movimento_credito)::decimal AS valor FROM msc.msc WHERE conta_contabil LIKE '%s' AND remessa = %d", $conta_contabil, $this->remessa));
            $msc_movimento_credito = round(array_sum(pg_fetch_all_columns($result, 0)), 2);
            $data['msc_movimento_credito'] = $msc_movimento_credito;
            
            $result = $this->query(sprintf("SELECT SUM(saldo_final)::decimal AS valor FROM msc.msc WHERE conta_contabil LIKE '%s' AND remessa = %d", $conta_contabil, $this->remessa));
            $msc_saldo_final = round(array_sum(pg_fetch_all_columns($result, 0)), 2);
            $data['msc_saldo_final'] = $msc_saldo_final;
            
            
            $data['diferenca_saldo_inicial'] = round($pad_saldo_inicial - $msc_saldo_inicial, 2);
            $data['diferenca_movimento_debito'] = round($pad_movimento_debito - $msc_movimento_debito, 2);
            $data['diferenca_movimento_credito'] = round($pad_movimento_credito - $msc_movimento_credito, 2);
            $data['diferenca_saldo_final'] = round($pad_saldo_final- $msc_saldo_final, 2);
            
            pg_insert($this->con, 'tmp.msc_balver', $data);
        }
        commit($this->con);
    }
    
    private function selecionaContasContabeis(): array {
        $sql = "WITH CC AS
                        (SELECT DISTINCT CONTA_CONTABIL_MSC AS CONTA_CONTABIL
                                FROM TMP.BALVER_MENSAL
                                UNION SELECT DISTINCT CONTA_CONTABIL
                                FROM MSC.MSC
                                WHERE REMESSA = %d )
                SELECT DISTINCT *
                FROM CC";
        $result = $this->query(sprintf($sql, $this->remessa));
        return pg_fetch_all_columns($result, 0);
    }
    
    private function buscaMapeamentos(): void {
        echo "\t-> Mapeando as contas PAD -> MSC...", PHP_EOL;
        $sql = "SELECT DISTINCT conta_contabil_pad FROM tmp.balver_mensal ORDER BY conta_contabil_pad ASC";
        $cc_pad = pg_fetch_all($this->query($sql), PGSQL_ASSOC);
        $pb = new \NickBeen\ProgressBar\ProgressBar(maxProgress: sizeof($cc_pad));
        begin_transaction($this->con);
        foreach ($pb->iterate($cc_pad) as $i => $row){
            $pad = $row['conta_contabil_pad'];
            $result = $this->query(sprintf("SELECT conta_contabil_msc FROM msc.mapeamento_cc WHERE conta_contabil_pad LIKE '%s'", $pad));
            if(pg_num_rows($result) === 0){
                $msc = substr($pad, 0, 9);
            }else{
                $data = pg_fetch_all($result, PGSQL_ASSOC);
                $msc = $data[0]['conta_contabil_msc'];
            }
            $this->query(sprintf("UPDATE tmp.balver_mensal SET conta_contabil_msc = '%s' WHERE conta_contabil_pad LIKE '%s'", $msc, $pad));
        }
        commit($this->con);
    }
    
    private function apagaBalVerMensal(): void {
        $this->query('DELETE FROM tmp.balver_mensal');
    }
    
    private function getDataFinal(): string {
        $data = date_create_from_format('Y-m-d', $this->getDataInicial());
        $data->modify('last day of this month'); // Modifica a data para o último dia do mês
        return $data->format('Y-m-d'); // Formata a data para o formato YYYY-MM-DD
    }
    
    private function getDataInicial(): string {
        $ano = (int) substr($this->remessa, 0, 4);
        $mes = (int) substr($this->remessa, 4, 2);
        if($mes === 13) $mes = 12;
        $data = date_create();
        $data->setDate($ano, $mes, 1);
        return $data->format('Y-m-d'); // Formata a data para o formato YYYY-MM-DD
    }
       
    private function calculaMovimentoDebitoBalVer(): void {
        echo "\t-> Calculando movimentação a débito...", PHP_EOL;
        $sql = "SELECT conta_contabil, SUM(valor_lancamento)::decimal AS movimento_debito FROM pad.tce_4111 WHERE remessa = %d AND tipo_lancamento LIKE 'D' AND (data_lancamento BETWEEN '%s' AND '%s') GROUP BY conta_contabil";
//        echo sprintf($sql, $this->remessa, $this->getDataInicial(), $this->getDataFinal()), PHP_EOL;exit();
        $result = $this->query(sprintf($sql, $this->remessa, $this->getDataInicial(), $this->getDataFinal()));
        $resultado = pg_fetch_all($result, PGSQL_ASSOC);
        $pb = new \NickBeen\ProgressBar\ProgressBar(maxProgress: sizeof($resultado));
        begin_transaction($this->con);
        foreach ($pb->iterate($resultado) as $i => $row){
            $data['conta_contabil_pad'] = $row['conta_contabil'];
            $data['movimento_debito'] = $row['movimento_debito'];
            pg_insert($this->con, 'tmp.balver_mensal', $data);
        }
        commit($this->con);
    }
    
    private function calculaMovimentoCreditoBalVer(): void {
        echo "\t-> Calculando movimentação a crédito...", PHP_EOL;
        $sql = "SELECT conta_contabil, SUM(valor_lancamento)::decimal AS movimento_credito FROM pad.tce_4111 WHERE remessa = %d AND tipo_lancamento LIKE 'C' AND (data_lancamento BETWEEN '%s' AND '%s') GROUP BY conta_contabil";
//        echo sprintf($sql, $this->remessa, $this->getDataInicial(), $this->getDataFinal()), PHP_EOL;exit();
        $result = $this->query(sprintf($sql, $this->remessa, $this->getDataInicial(), $this->getDataFinal()));
        $resultado = pg_fetch_all($result, PGSQL_ASSOC);
        $pb = new \NickBeen\ProgressBar\ProgressBar(maxProgress: sizeof($resultado));
        begin_transaction($this->con);
        foreach ($pb->iterate($resultado) as $i => $row){
            $data['conta_contabil_pad'] = $row['conta_contabil'];
            $data['movimento_credito'] = $row['movimento_credito'];
            pg_insert($this->con, 'tmp.balver_mensal', $data);
        }
        commit($this->con);
    }
    
    private function calculaSaldoInicialBalVer(string $tabela): void {
        echo "\t-> Calculando saldo inicial...", PHP_EOL;
        $sql = "SELECT conta_contabil, saldo_atual::decimal FROM %s WHERE remessa = %d AND escrituracao LIKE 'S'";
        $remessa_anterior = $this->remessaAnterior();
        if(substr($remessa_anterior, 4, 2) == 13){
            $remessa_anterior = substr($remessa_anterior, 0, 4).'12';
            $tabela = 'pad.bver_enc';
        }
        if(substr($this->remessa, 4,2) == 13){
            $tabela = 'pad.bal_ver';
        }
        $result = $this->query(sprintf($sql, $tabela, $remessa_anterior));
        $resultado = pg_fetch_all($result, PGSQL_ASSOC);
        $pb = new \NickBeen\ProgressBar\ProgressBar(maxProgress: sizeof($resultado));
        begin_transaction($this->con);
        foreach ($pb->iterate($resultado) as $i => $row){
            $data['conta_contabil_pad'] = $row['conta_contabil'];
            $data['saldo_inicial'] = $row['saldo_atual'];
            pg_insert($this->con, 'tmp.balver_mensal', $data);
        }
        commit($this->con);
    }
    
    private function calculaSaldoFinalBalVer(string $tabela): void {
        echo "\t-> Calculando saldo final...", PHP_EOL;
        $sql = "SELECT conta_contabil, saldo_atual::decimal FROM %s WHERE remessa = %d AND escrituracao LIKE 'S'";
//        echo sprintf($sql, $tabela, $this->remessaAnterior()), PHP_EOL;exit();
        $result = $this->query(sprintf($sql, $tabela, $this->remessa));
        $resultado = pg_fetch_all($result, PGSQL_ASSOC);
        $pb = new \NickBeen\ProgressBar\ProgressBar(maxProgress: sizeof($resultado));
        begin_transaction($this->con);
        foreach ($pb->iterate($resultado) as $i => $row){
            $data['conta_contabil_pad'] = $row['conta_contabil'];
            $data['saldo_final'] = $row['saldo_atual'];
            pg_insert($this->con, 'tmp.balver_mensal', $data);
        }
        commit($this->con);
    }
    
    private function montaBalVerEncerramento(): void {
        $tabela = 'pad.bver_enc';
        $this->apagaBalVerMensal();
//        $this->calculaSaldoInicialBalVer($tabela);
//        $this->calculaMovimentoDebitoBalVer();
//        $this->calculaMovimentoCreditoBalVer();
//        $this->calculaSaldoFinalBalVer($tabela);
        
        echo "\t-> Montando balancete mensal de encerramento...", PHP_EOL;
        $sql = "WITH CC AS
	(SELECT DISTINCT CONTA_CONTABIL
		FROM PAD.BVER_ENC
		WHERE REMESSA = %d
			AND ESCRITURACAO like 'S' ),
	BALANCETE AS
	(SELECT CONTA_CONTABIL,

			(SELECT SUM(SALDO_ATUAL)::decimal
				FROM PAD.BAL_VER
				WHERE REMESSA = %d
					AND ESCRITURACAO like 'S'
					AND CONTA_CONTABIL like CC.CONTA_CONTABIL) AS SALDO_INICIAL,

			(SELECT SUM(MOVIMENTO_DEVEDOR)::decimal
				FROM PAD.BAL_VER
				WHERE REMESSA = %d
					AND ESCRITURACAO like 'S'
					AND CONTA_CONTABIL like CC.CONTA_CONTABIL ) AS DEBITO_INICIAL,

			(SELECT SUM(MOVIMENTO_DEVEDOR)::decimal
				FROM PAD.BVER_ENC
				WHERE REMESSA = %d
					AND ESCRITURACAO like 'S'
					AND CONTA_CONTABIL like CC.CONTA_CONTABIL ) AS DEBITO_FINAL,

			(SELECT SUM(MOVIMENTO_CREDOR)::decimal
				FROM PAD.BAL_VER
				WHERE REMESSA = %d
					AND ESCRITURACAO like 'S'
					AND CONTA_CONTABIL like CC.CONTA_CONTABIL ) AS CREDITO_INICIAL,

			(SELECT SUM(MOVIMENTO_CREDOR)::decimal
				FROM PAD.BVER_ENC
				WHERE REMESSA = %d
					AND ESCRITURACAO like 'S'
					AND CONTA_CONTABIL like CC.CONTA_CONTABIL ) AS CREDITO_FINAL,

			(SELECT SUM(SALDO_ATUAL)::decimal
				FROM PAD.BVER_ENC
				WHERE REMESSA = %d
					AND ESCRITURACAO like 'S'
					AND CONTA_CONTABIL like CC.CONTA_CONTABIL) AS SALDO_FINAL
		FROM CC
		ORDER BY CONTA_CONTABIL ASC)
SELECT CONTA_CONTABIL,
	SALDO_INICIAL::DECIMAL,
	(DEBITO_FINAL - DEBITO_INICIAL)::DECIMAL AS MOVIMENTO_DEBITO,
	(CREDITO_FINAL - CREDITO_INICIAL)::DECIMAL AS MOVIMENTO_CREDITO,
	SALDO_FINAL::DECIMAL
FROM BALANCETE
ORDER BY CONTA_CONTABIL";
        $remessa = substr($this->remessa, 0, 4).'12';
        $result = $this->query(sprintf($sql, $remessa, $remessa, $remessa, $remessa, $remessa, $remessa, $remessa));
        $resultado = pg_fetch_all($result, PGSQL_ASSOC);
        $pb = new \NickBeen\ProgressBar\ProgressBar(maxProgress: sizeof($resultado));
        begin_transaction($this->con);
        foreach ($pb->iterate($resultado) as $i => $row){
            $data['conta_contabil_pad'] = $row['conta_contabil'];
            $data['saldo_inicial'] = $row['saldo_inicial'];
            $data['movimento_debito'] = $row['movimento_debito'];
            $data['movimento_credito'] = $row['movimento_credito'];
            $data['saldo_final'] = $row['saldo_final'];
            pg_insert($this->con, 'tmp.balver_mensal', $data);
        }
        commit($this->con);
        $this->buscaMapeamentos();
    }
    
    
    
    private function salvaResultado(): void {
        echo "\t-> Salvando resultados no DB...", PHP_EOL;
        $cc = $this->selecionaContasContabeis();
        $this->calculaDiferencas($cc);
    }
    
    protected function salvaRelatorio(array $header): void {
        echo "\t-> Salvando relatório em arquivo...", PHP_EOL;
        $resultado = pg_fetch_all($this->query('SELECT * FROM tmp.msc_balver WHERE diferenca_saldo_inicial::numeric != 0 OR diferenca_saldo_final::numeric != 0 OR diferenca_movimento_debito::numeric != 0 OR diferenca_movimento_credito::numeric != 0'), PGSQL_ASSOC);
        $progressBar = new \NickBeen\ProgressBar\ProgressBar(maxProgress: sizeof($resultado));
        $progressBar->start();
        $fhandler = fopen($this->report, 'w');
        fputcsv($fhandler, $header, ';');
        foreach ($resultado as $row){
            fputcsv($fhandler, $row, ';');
            $progressBar->tick();
        }
        fclose($fhandler);
        $progressBar->finish();
    }
}
