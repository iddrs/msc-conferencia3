function Show-Menu {
    param (
        [string]$Title = 'Conferência da MSC'
    )
    Clear-Host
    Write-Host "================ $Title ================"
    
    Write-Host "1: Saldo Final Anterior x Saldo Inicial Atual"
    Write-Host "2: Consistência do Saldo Final"
    Write-Host "3: Balancete Contábil x MSC"
    Write-Host "4: Todos os testes"
    Write-Host "Q: Sair"
}

do {
    Show-Menu
    $input = Read-Host "Por favor, escolha uma opção"
    switch ($input) {
        '1' {
            Write-Host "Você escolheu a Opção 1: Saldo Final Anterior x Saldo Inicial Atual"
            # Aqui você pode adicionar o código para executar a opção 1
            php saldo_anterior_atual.php
        }
        '2' {
            Write-Host "Você escolheu a Opção 2: Consistência do Saldo Final"
            # Aqui você pode adicionar o código para executar a opção 2
            php saldo_final_calculado.php
        }
        '3' {
            Write-Host "Você escolheu a Opção 3: Balancete Contábil x MSC"
            # Aqui você pode adicionar o código para executar a opção 3
            php msc_balver.php
        }
        '4' {
            Write-Host "Você escolheu a Opção 4: Todos os testes"
            # Aqui você pode adicionar o código para executar a opção 4
            php todos.php
        }
        'q' {
            Write-Host "Saindo... Obrigado."
            return
        }
    }
    pause
}
until ($input -eq 'q')

