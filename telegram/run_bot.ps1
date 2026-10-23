$secureToken = Read-Host 'Enter your NEW BotFather token (input is masked)' -AsSecureString
$tokenPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureToken)

try {
    $env:TELEGRAM_BOT_TOKEN = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($tokenPointer)
    $env:TELEGRAM_ADMIN_IDS = Read-Host 'Enter your numeric Telegram user ID'

    if ($env:TELEGRAM_ADMIN_IDS -notmatch '^\d{1,20}$') {
        throw 'The admin Telegram ID must contain only digits.'
    }

    & 'C:\xampp\php\php.exe' (Join-Path $PSScriptRoot 'poll.php')
    exit $LASTEXITCODE
}
finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($tokenPointer)
    Remove-Item Env:TELEGRAM_BOT_TOKEN -ErrorAction SilentlyContinue
    Remove-Item Env:TELEGRAM_ADMIN_IDS -ErrorAction SilentlyContinue
}