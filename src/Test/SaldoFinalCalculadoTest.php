<?php

/**
 * Testa se o saldo final corresponde ao resultado de saldo inicial + débitos - créditos.
 * 
 * Considera as contas contábeis qualificadas.
 */

namespace App\Test;

class SaldoFinalCalculadoTest extends BaseTest {
    
    public function __construct(\PgSql\Connection $con, int $remessa) {
        $file_name = explode('\\', __CLASS__);
        $report = './report/'.array_pop($file_name).'.csv';
        parent::__construct($con, $remessa, $report);
    }
       
    public function run(): void {
        $sql = "WITH TMP1 AS
                        (SELECT CONTA_CONTABIL,
                                        PODER_ORGAO,
                                        FINANCEIRO_PERMANENTE,
                                        DIVIDA_CONSOLIDADA,
                                        INDICADOR_EXERCICIO_FONTE_RECURSO,
                                        FONTE_RECURSO,
                                        CODIGO_ACOMPANHAMENTO_ORCAMENTARIO,
                                        NATUREZA_RECEITA,
                                        NATUREZA_DESPESA,
                                        FUNCAO,
                                        SUBFUNCAO,
                                        ANO_INSCRICAO_RESTOS_A_PAGAR,
                                        CASE SUBSTRING(CONTA_CONTABIL FROM 1 FOR 1)
                                                                        WHEN '1' THEN SALDO_INICIAL::decimal
                                                                        WHEN '3' THEN SALDO_INICIAL::decimal
                                                                        WHEN '5' THEN SALDO_INICIAL::decimal
                                                                        WHEN '7' THEN SALDO_INICIAL::decimal
                                                                        WHEN '2' THEN (SALDO_INICIAL * -1)::decimal
                                                                        WHEN '4' THEN (SALDO_INICIAL * -1)::decimal
                                                                        WHEN '6' THEN (SALDO_INICIAL * -1)::decimal
                                                                        WHEN '8' THEN (SALDO_INICIAL * -1)::decimal
                                        END SALDO_INICIAL,
                                        MOVIMENTO_DEBITO::decimal,
                                        MOVIMENTO_CREDITO::decimal,
                                        --CASE SUBSTRING(CONTA_CONTABIL FROM 1 FOR 1)
                                        --                                WHEN '1' THEN SALDO_FINAL::decimal
                                        --                                WHEN '3' THEN SALDO_FINAL::decimal
                                        --                                WHEN '5' THEN SALDO_FINAL::decimal
                                        --                                WHEN '7' THEN SALDO_FINAL::decimal
                                        --                                WHEN '2' THEN (SALDO_FINAL * -1)::decimal
                                        --                                WHEN '4' THEN (SALDO_FINAL * -1)::decimal
                                        --                                WHEN '6' THEN (SALDO_FINAL * -1)::decimal
                                        --                                WHEN '8' THEN (SALDO_FINAL * -1)::decimal
                                        --END SALDO_FINAL,
                                        SALDO_FINAL::decimal,
                                        CASE SUBSTRING(CONTA_CONTABIL FROM 1 FOR 1)
                                                                        WHEN '1' THEN (SALDO_INICIAL + MOVIMENTO_DEBITO - MOVIMENTO_CREDITO)::decimal
                                                                        WHEN '3' THEN (SALDO_INICIAL + MOVIMENTO_DEBITO - MOVIMENTO_CREDITO)::decimal
                                                                        WHEN '5' THEN (SALDO_INICIAL + MOVIMENTO_DEBITO - MOVIMENTO_CREDITO)::decimal
                                                                        WHEN '7' THEN (SALDO_INICIAL + MOVIMENTO_DEBITO - MOVIMENTO_CREDITO)::decimal
                                                                        WHEN '2' THEN (SALDO_INICIAL - MOVIMENTO_DEBITO + MOVIMENTO_CREDITO)::decimal
                                                                        WHEN '4' THEN (SALDO_INICIAL - MOVIMENTO_DEBITO + MOVIMENTO_CREDITO)::decimal
                                                                        WHEN '6' THEN (SALDO_INICIAL - MOVIMENTO_DEBITO + MOVIMENTO_CREDITO)::decimal
                                                                        WHEN '8' THEN (SALDO_INICIAL - MOVIMENTO_DEBITO + MOVIMENTO_CREDITO)::decimal
                                        END SALDO_FINAL_CALCULADO
                                FROM MSC.MSC
                                WHERE REMESSA = %d ),
                        TMP2 AS
                        (SELECT *,
                                        (SALDO_FINAL - SALDO_FINAL_CALCULADO)::decimal AS DIFERENCA
                                FROM TMP1)
                SELECT *
                FROM TMP2";
        $resultado = $this->query(sprintf($sql, $this->remessa));
        
//        print_r(pg_fetch_all($resultado, PGSQL_ASSOC)); exit();
        $this->salvaResultado(pg_fetch_all($resultado, PGSQL_ASSOC));
        
        $header = [
            'conta_contabil',
            'poder_orgao',
            'financeiro_permanente',
            'divida_consolidada',
            'indicador_exercicio_fonte_recurso',
            'fonte_recurso',
            'codigo_acompanhamento_orcamentario',
            'natureza_receita',
            'natureza_despesa',
            'funcao',
            'subfuncao',
            'ano_inscricao_restos_a_pagar',
            'saldo_inicial',
            'movimento_debito',
            'movimento_credito',
            'saldo_final',
            'saldo_final_calculado',
            'diferenca'
        ];
        $this->salvaRelatorio($header);
    }
    
    private function salvaResultado(array $resultado): void {
        echo "\t\t-> Salvando resultados no DB...", PHP_EOL;
        $progressBar = new \NickBeen\ProgressBar\ProgressBar(maxProgress: sizeof($resultado));
        $progressBar->start();
        begin_transaction($this->con);
        $this->query('DELETE FROM tmp.msc_saldo_final_calculado');
        foreach ($resultado as $row){
            if(!pg_insert($this->con, 'tmp.msc_saldo_final_calculado', $row)){
                trigger_error('Falha ao tentar inserir registro em tmp.msc_saldo_final_calculado', E_USER_ERROR);
            }
            $progressBar->tick();
        }
        commit($this->con);
        $progressBar->finish();
    }
    
    protected function salvaRelatorio(array $header): void {
        echo "\t\t-> Salvando relatório em arquivo...", PHP_EOL;
        $resultado = pg_fetch_all($this->query('SELECT * FROM tmp.msc_saldo_final_calculado WHERE diferenca::numeric != 0'), PGSQL_ASSOC);
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
