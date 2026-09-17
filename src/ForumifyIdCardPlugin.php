<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard;

use Forumify\Plugin\AbstractForumifyPlugin;
use Forumify\Plugin\PluginMetadata;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

class ForumifyIdCardPlugin extends AbstractForumifyPlugin
{
    public function getPluginMetadata(): PluginMetadata
    {
        return new PluginMetadata('ID Cards', 'MajesticDev', 'Create and manage MILSIM identification cards with optional MILHQ personnel integration.', settingsRoute: 'id_cards_settings');
    }

    public function getPermissions(): array
    {
        return ['admin' => ['id_cards' => ['view', 'create', 'manage', 'revoke', 'delete', 'download'], 'id_card_configuration' => ['manage', 'unit_mapping']]];
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        parent::prependExtension($container, $builder);
        $container->extension('doctrine', ['orm' => ['mappings' => ['ForumifyIdCardPlugin' => [
            'is_bundle' => false, 'type' => 'attribute', 'dir' => $this->getPath().'/src/Entity', 'prefix' => __NAMESPACE__.'\\Entity',
        ]]]]);
        $container->extension('doctrine_migrations', ['migrations_paths' => ['MajesticDevIdCardMigrations' => $this->getPath().'/migrations']]);
        // /id/{token} is public and unauthenticated (a QR code scan), so Forumify's own
        // SimpleRateLimiter doesn't apply here - it no-ops for anonymous users by design.
        // Effectively unlimited under test: the HTTP suite hits this route several times
        // per test and shouldn't have to account for throttling.
        $container->extension('framework', ['rate_limiter' => ['id_cards.verify' => $container->env() === 'test'
            ? ['policy' => 'fixed_window', 'limit' => 999999, 'interval' => '1 second']
            : ['policy' => 'sliding_window', 'limit' => 20, 'interval' => '1 minute'],
        ]]);
    }
}
