<?php

namespace App\Test;

/**
 * Classe base para os testes
 *
 * @author Everton
 */
abstract class BaseTest {
    
    protected \PgSql\Connection $con;
    
    protected int $remessa;
    
    protected int $mes;
    
    protected int $ano;
    
    protected string $report;

    public function __construct(\PgSql\Connection $con, int $remessa, string $report) {
        $this->con= $con;
        $this->remessa = $remessa;
        $this->mes = (int) substr($remessa, 4, 2);
        $this->ano = (int) substr($remessa, 0, 4);
        $this->report = $report;
    }
    
    protected function query(string $sql): \PgSql\Result {
        return pg_query($this->con, $sql);
    }
    
    protected function remessaAnterior(): int {
        $ano = $this->ano;
        $mes = $this->mes - 1;
        if ($mes === 0){
            $ano = $this->ano - 1;
            $mes = 13;
        }
        
        return (int) sprintf('%s%s', $ano, str_pad($mes, 2, '0', STR_PAD_LEFT));
    }
    
    abstract public function run(): void;
}
