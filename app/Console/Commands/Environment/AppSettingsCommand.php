<?php

namespace Pterodactyl\Console\Commands\Environment;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Pterodactyl\Traits\Commands\EnvironmentWriterTrait;

class AppSettingsCommand extends Command
{
    use EnvironmentWriterTrait;

    public const CACHE_DRIVERS = [
        'redis' => 'Redis (recomendado)',
        'memcached' => 'Memcached',
        'file' => 'Filesystem',
    ];

    public const SESSION_DRIVERS = [
        'redis' => 'Redis (recomendado)',
        'memcached' => 'Memcached',
        'database' => 'MySQL Database',
        'file' => 'Filesystem',
        'cookie' => 'Cookie',
    ];

    public const QUEUE_DRIVERS = [
        'redis' => 'Redis (recomendado)',
        'database' => 'MySQL Database',
        'sync' => 'Sync',
    ];

    protected $description = 'Defina configurações básicas de ambiente para o painel.';

    protected $signature = 'p:environment:setup
                            {--new-salt : Se deve ou não gerar um novo salt para hashids.}
                            {--author= : O E-mail que os serviços criados nesta instância devem estar vinculados.}
                            {--url= : O URL em que este painel estará sendo executado.}
                            {--timezone= : O fuso horário a ser usado para os tempos do painel.}
                            {--cache= : O back-end do driver de cache a ser usado.}
                            {--session= : O back-end do driver da sessão para usar.}
                            {--queue= : O back-end do driver da fila para usar.}
                            {--redis-host= : Host do redis  a ser usado para conexões.}
                            {--redis-pass= : Senha usada para conectar-se ao Redis.}
                            {--redis-port= : Porta para conectar-se ao Redis.}
                            {--settings-ui= : Ativar ou desativar as configurações UI.}';

    protected array $variables = [];

    /**
     * AppSettingsCommand constructor.
     */
    public function __construct(private Kernel $console)
    {
        parent::__construct();
    }

    /**
     * Handle command execution.
     *
     * @throws \Pterodactyl\Exceptions\PterodactylException
     */
    public function handle(): int
    {
        if (empty(config('hashids.salt')) || $this->option('new-salt')) {
            $this->variables['HASHIDS_SALT'] = str_random(20);
        }

        $this->output->comment('Forneça o endereço de E-mail que será usado para exportar novos eggs . Este deve ser um endereço de E-mail válido.');
        $this->variables['APP_SERVICE_AUTHOR'] = $this->option('author') ?? $this->ask(
            'E-mail do autor do Egg',
            config('pterodactyl.service.author', 'unknown@unknown.com')
        );

        if (!filter_var($this->variables['APP_SERVICE_AUTHOR'], FILTER_VALIDATE_EMAIL)) {
            $this->output->error('O E-mail do autor de serviço fornecido é inválido.');

            return 1;
        }

        $this->output->comment('A URL do aplicativo(painel) DEVE começar com https:// ou http:// dependendo se você estiver usando SSL ou não. Se você não incluir o esquema, seus E-mails e outros conteúdos serão vinculados ao local errado.');
        $this->variables['APP_URL'] = $this->option('url') ?? $this->ask(
            'Aplicativo(painel) URL',
            config('app.url', 'https://example.com')
        );

        $this->output->comment('O fuso horário deve corresponder a um dos fusos horários suportados pelo PHP. Se você não tiver certeza, por favor consulte https://php.net/manual/en/timezones.php.');
        $this->variables['APP_TIMEZONE'] = $this->option('timezone') ?? $this->anticipate(
            'Application Timezone',
            \DateTimeZone::listIdentifiers(),
            config('app.timezone')
        );

        $selected = config('cache.default', 'redis');
        $this->variables['CACHE_DRIVER'] = $this->option('cache') ?? $this->choice(
            ' Driver de Cache',
            self::CACHE_DRIVERS,
            array_key_exists($selected, self::CACHE_DRIVERS) ? $selected : null
        );

        $selected = config('session.driver', 'redis');
        $this->variables['SESSION_DRIVER'] = $this->option('session') ?? $this->choice(
            'Driver de Sessão',
            self::SESSION_DRIVERS,
            array_key_exists($selected, self::SESSION_DRIVERS) ? $selected : null
        );

        $selected = config('queue.default', 'redis');
        $this->variables['QUEUE_CONNECTION'] = $this->option('queue') ?? $this->choice(
            'Driver de Queue',
            self::QUEUE_DRIVERS,
            array_key_exists($selected, self::QUEUE_DRIVERS) ? $selected : null
        );

        if (!is_null($this->option('settings-ui'))) {
            $this->variables['APP_ENVIRONMENT_ONLY'] = $this->option('settings-ui') == 'true' ? 'false' : 'true';
        } else {
            $this->variables['APP_ENVIRONMENT_ONLY'] = $this->confirm('Habilitar o editor de configurações baseado na IU?', true) ? 'false' : 'true';
        }

        // Make sure session cookies are set as "secure" when using HTTPS
        if (str_starts_with($this->variables['APP_URL'], 'https://')) {
            $this->variables['SESSION_SECURE_COOKIE'] = 'true';
        }

        $this->checkForRedis();
        $this->writeToEnvironment($this->variables);

        $this->info($this->console->output());

        return 0;
    }

    /**
     * Check if redis is selected, if so, request connection details and verify them.
     */
    private function checkForRedis()
    {
        $items = collect($this->variables)->filter(function ($item) {
            return $item === 'redis';
        });

        // Redis was not selected, no need to continue.
        if (count($items) === 0) {
            return;
        }

        $this->output->note('You\'ve selecionado o driver Redis para uma ou mais opções, por favor, forneça informações de conexão válidas abaixo. Na maioria dos casos, você pode usar os padrões fornecidos, a menos que tenha modificado sua configuração.');
        $this->variables['REDIS_HOST'] = $this->option('redis-host') ?? $this->ask(
            'Host do Redis ',
            config('database.redis.default.host')
        );

        $askForRedisPassword = true;
        if (!empty(config('database.redis.default.password'))) {
            $this->variables['REDIS_PASSWORD'] = config('database.redis.default.password');
            $askForRedisPassword = $this->confirm('Parece que uma senha já está definida para o Redis, você gostaria de alterá-la?');
        }

        if ($askForRedisPassword) {
            $this->output->comment('Por padrão, uma instância do servidor Redis não tem senha, pois está sendo executada localmente e inacessível ao mundo exterior. Se este for o caso, basta pressionar enter sem inserir um valor.');
            $this->variables['REDIS_PASSWORD'] = $this->option('redis-pass') ?? $this->output->askHidden(
                'Senha do Redis'
            );
        }

        if (empty($this->variables['REDIS_PASSWORD'])) {
            $this->variables['REDIS_PASSWORD'] = 'null';
        }

        $this->variables['REDIS_PORT'] = $this->option('redis-port') ?? $this->ask(
            'Porta do Redis',
            config('database.redis.default.port')
        );
    }
}
