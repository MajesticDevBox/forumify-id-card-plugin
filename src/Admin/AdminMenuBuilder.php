<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Admin;

use Forumify\Admin\MenuBuilder\AdminMenuBuilderInterface;
use Forumify\Core\MenuBuilder\Menu;
use Forumify\Core\MenuBuilder\MenuItem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AdminMenuBuilder implements AdminMenuBuilderInterface
{
    public function __construct(private readonly UrlGeneratorInterface $router) {}

    public function build(Menu $menu): void
    {
        $items = [];
        foreach ([['Cards', 'id_cards_list', 'id_cards.view'], ['Create Card', 'id_cards_create', 'id_cards.create'], ['Unit Mapping', 'id_cards_mappings', 'id_card_configuration.unit_mapping'], ['Settings', 'id_cards_settings', 'id_card_configuration.manage']] as [$label, $route, $permission]) {
            $items[] = new MenuItem($label, $this->router->generate($route), ['permission' => 'id-cards.admin.'.$permission]);
        }
        $menu->addItem(new Menu('Identification Cards', ['icon' => 'ph ph-identification-card'], $items));
    }
}
