<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Tests;

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use MajesticDev\ForumifyIdCard\Service\CardSettings;

class TestKernel extends Kernel
{
    use MicroKernelTrait;
    public function registerBundles(): iterable
    {
        yield new \Symfony\Bundle\FrameworkBundle\FrameworkBundle();
        yield new \Symfony\Bundle\SecurityBundle\SecurityBundle();
        yield new \Symfony\Bundle\TwigBundle\TwigBundle();
        yield new \Doctrine\Bundle\DoctrineBundle\DoctrineBundle();
        yield new \Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle();
        yield new \MajesticDev\ForumifyIdCard\ForumifyIdCardPlugin();
    }
    public function getProjectDir(): string { return dirname(__DIR__); }
    public function getCacheDir(): string { return $this->getProjectDir().'/var/cache/'.$this->environment; }
    public function getLogDir(): string { return $this->getProjectDir().'/var/log'; }

    protected function configureContainer(ContainerConfigurator $c): void
    {
        $c->extension('framework', ['secret' => 'test-only', 'test' => true, 'router' => ['utf8' => true], 'form' => true, 'csrf_protection' => true, 'validation' => ['enable_attributes' => true], 'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'], 'assets' => true, 'translator' => ['fallbacks' => ['en']]]);
        $c->extension('doctrine', ['dbal' => ['driver' => 'pdo_sqlite', 'path' => '%kernel.project_dir%/var/test.sqlite'], 'orm' => ['auto_generate_proxy_classes' => true, 'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware']]);
        $c->extension('twig', ['paths' => [__DIR__.'/templates' => 'Forumify'], 'form_themes' => ['form_div_layout.html.twig']]);
        $c->extension('security', ['providers' => ['test' => ['memory' => ['users' => ['admin' => ['password' => 'unused', 'roles' => ['ROLE_ADMIN']], 'viewer' => ['password' => 'unused', 'roles' => ['ROLE_USER']]]]]], 'firewalls' => ['main' => ['lazy' => true, 'provider' => 'test']]]);
        $c->services()->set(CardSettings::class, TestSettings::class)->public();
        $c->services()->set(TestPermissionVoter::class)->tag('security.voter');
        $c->services()->set('logger', \Psr\Log\NullLogger::class);
    }
    protected function configureRoutes(RoutingConfigurator $routes): void { $routes->import(dirname(__DIR__).'/config/routes.yaml'); }
}
