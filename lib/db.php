<?php

function connect(string $connectionString): PgSql\Connection {
    $con = pg_connect($connectionString);
    if (!$con) {
        $error = pg_last_error($this->con);
        trigger_error("Falha ao conectar com {$connectionString}: {$error}", E_USER_ERROR);
    }
    return $con;
}

function begin_transaction(PgSql\Connection $con): void {
    if (!pg_query($con, 'BEGIN')) {
        $error = pg_last_error($con);
        trigger_error("Falha ao iniciar a transação: {$error}", E_USER_ERROR);
    }
}

function commit(\PgSql\Connection $con): void {
    if (!pg_query($con, 'COMMIT')) {
        $error = pg_last_error($con);
        trigger_error("Falha ao confirmar a transação: {$error}", E_USER_ERROR);
    }
}
