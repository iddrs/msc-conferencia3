<?php

/**
 * Testa se o saldo final do mês anterior é igual ao saldo inicial do mês em teste.
 * 
 * Considera as contas contábeis qualificadas.
 */

namespace App\Test;

class SaldoAnteriorAtualTest extends BaseTest {
    
    public function __construct(\PgSql\Connection $con, int $remessa) {
        $file_name = explode('\\', __CLASS__);
        $report = './report/'.array_pop($file_name).'.csv';
        parent::__construct($con, $remessa, $report);
    }
    
    private function listarContas(): array {
        $sql = "WITH LISTA_CC AS
                        (SELECT DISTINCT CONTA_CONTABIL,
                                        PODER_ORGAO,
                                        COALESCE(FINANCEIRO_PERMANENTE, 0) AS FINANCEIRO_PERMANENTE,
                                        COALESCE(DIVIDA_CONSOLIDADA, 0) AS DIVIDA_CONSOLIDADA,
                                        COALESCE(INDICADOR_EXERCICIO_FONTE_RECURSO, 0) AS INDICADOR_EXERCICIO_FONTE_RECURSO,
                                        COALESCE(FONTE_RECURSO, 0) AS FONTE_RECURSO,
                                        COALESCE(CODIGO_ACOMPANHAMENTO_ORCAMENTARIO, 0) AS CODIGO_ACOMPANHAMENTO_ORCAMENTARIO,
                                        COALESCE(NATUREZA_RECEITA, '0') AS NATUREZA_RECEITA,
                                        COALESCE(NATUREZA_DESPESA, '0') AS NATUREZA_DESPESA,
                                        COALESCE(FUNCAO, 0) AS FUNCAO,
                                        COALESCE(SUBFUNCAO, 0) AS SUBFUNCAO,
                                        COALESCE(ANO_INSCRICAO_RESTOS_A_PAGAR, 0) AS ANO_INSCRICAO_RESTOS_A_PAGAR
                                FROM MSC.MSC
                                WHERE REMESSA = 202401
                                UNION SELECT DISTINCT CONTA_CONTABIL,
                                        PODER_ORGAO,
                                        COALESCE(FINANCEIRO_PERMANENTE, 0) AS FINANCEIRO_PERMANENTE,
                                        COALESCE(DIVIDA_CONSOLIDADA, 0) AS DIVIDA_CONSOLIDADA,
                                        COALESCE(INDICADOR_EXERCICIO_FONTE_RECURSO, 0) AS INDICADOR_EXERCICIO_FONTE_RECURSO,
                                        COALESCE(FONTE_RECURSO, 0) AS FONTE_RECURSO,
                                        COALESCE(CODIGO_ACOMPANHAMENTO_ORCAMENTARIO, 0) AS CODIGO_ACOMPANHAMENTO_ORCAMENTARIO,
                                        COALESCE(NATUREZA_RECEITA, '0') AS NATUREZA_RECEITA,
                                        COALESCE(NATUREZA_DESPESA, '0') AS NATUREZA_DESPESA,
                                        COALESCE(FUNCAO, 0) AS FUNCAO,
                                        COALESCE(SUBFUNCAO, 0) AS SUBFUNCAO,
                                        COALESCE(ANO_INSCRICAO_RESTOS_A_PAGAR, 0) AS ANO_INSCRICAO_RESTOS_A_PAGAR
                                FROM MSC.MSC
                                WHERE REMESSA = 202402 )
                SELECT DISTINCT *
                FROM LISTA_CC";
        
        $result = $this->query(sprintf($sql, $this->remessa, $this->remessaAnterior()));
        return pg_fetch_all($result, PGSQL_ASSOC);
    }
    
    private function preparaColunas(array $contas): array {
        foreach ($contas as $i => $row) {
            $contas[$i]['saldo_final_anterior'] = 0.0;
            $contas[$i]['saldo_inicial_atual'] = 0.0;
            $contas[$i]['diferenca'] = 0.0;
        }
        return $contas;
    }
    
    private function calculaValores(array $row): array {
        $sql = "SELECT SUM(SALDO_FINAL) AS VALOR FROM MSC.MSC "
                . "WHERE REMESSA = %d "
                . "AND CONTA_CONTABIL LIKE '%s' "
                . "AND FINANCEIRO_PERMANENTE = %d "
                . "AND DIVIDA_CONSOLIDADA = %d "
                . "AND INDICADOR_EXERCICIO_FONTE_RECURSO = %d "
                . "AND FONTE_RECURSO = %d "
                . "AND CODIGO_ACOMPANHAMENTO_ORCAMENTARIO = %d "
                . "AND NATUREZA_RECEITA LIKE '%s' "
                . "AND NATUREZA_DESPESA LIKE '%s' "
                . "AND FUNCAO = %d "
                . "AND SUBFUNCAO = %d "
                . "AND ANO_INSCRICAO_RESTOS_A_PAGAR = %d";
        $valor = $this->query(sprintf($sql, $this->remessa, $row['conta_contabil'], $row['financeiro_permanente'], $row['divida_consolidada'], $row['indicador_exercicio_fonte_recurso'], $row['fonte_recurso'], $row['codigo_acompanhamento_orcamentario'], $row['natureza_receita'], $row['natureza_despesa'], $row['funcao'], $row['subfuncao'], $row['ano_inscricao_restos_a_pagar']));
        if($valor === false){
            $saldo_final_anterior = 0.0;
        }else{
            $saldo_final_anterior = round(array_sum(pg_fetch_all_columns($valor, 0)), 2);
        }
        
        $valor = $this->query(sprintf($sql, $this->remessaAnterior(), $row['conta_contabil'], $row['financeiro_permanente'], $row['divida_consolidada'], $row['indicador_exercicio_fonte_recurso'], $row['fonte_recurso'], $row['codigo_acompanhamento_orcamentario'], $row['natureza_receita'], $row['natureza_despesa'], $row['funcao'], $row['subfuncao'], $row['ano_inscricao_restos_a_pagar']));
        if($valor === false){
            $saldo_inicial_atual = 0.0;
        }else{
            $saldo_inicial_atual = round(array_sum(pg_fetch_all_columns($valor, 0)), 2);
        }
        
        $diferenca = round($saldo_inicial_atual - $saldo_final_anterior, 2);
        
        $row['saldo_final_anterior'] = $saldo_final_anterior;
        $row['saldo_inicial_atual'] = $saldo_inicial_atual;
        $row['diferenca'] = $diferenca;
        return $row;
    }
    
    public function run(): void {
        echo "\t\t-> Processando contas...", PHP_EOL;
        $contas = $this->preparaColunas($this->listarContas());
        $progressBar = new \NickBeen\ProgressBar\ProgressBar(maxProgress: sizeof($contas));
        $progressBar->start();
        foreach ($contas as $i => $row){
            $progressBar->tick();
            $contas[$i] = $this->calculaValores($row);
        }
        $progressBar->finish();
        
        $this->salvaResultado($contas);
        
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
            'saldo_final_anterior',
            'saldo_inicial_atual',
            'diferenca'
        ];
        $this->salvaRelatorio($header);
    }
    
    private function salvaResultado(array $resultado): void {
        echo "\t\t-> Salvando resultados no DB...", PHP_EOL;
        $progressBar = new \NickBeen\ProgressBar\ProgressBar(maxProgress: sizeof($resultado));
        $progressBar->start();
        begin_transaction($this->con);
        $this->query('DELETE FROM tmp.msc_saldo_final_inicial');
        foreach ($resultado as $row){
            if(!pg_insert($this->con, 'tmp.msc_saldo_final_inicial', $row)){
                trigger_error('Falha ao tentar inserir registro em tmp.msc_saldo_final_inicial', E_USER_ERROR);
            }
            $progressBar->tick();
        }
        commit($this->con);
        $progressBar->finish();
    }
    
    protected function salvaRelatorio(array $header): void {
        echo "\t\t-> Salvando relatório em arquivo...", PHP_EOL;
        $resultado = pg_fetch_all($this->query('SELECT * FROM tmp.msc_saldo_final_inicial WHERE diferenca::numeric != 0'), PGSQL_ASSOC);
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
