<?php

namespace Wave\View;

use Twig\Extension;

class TwigExtension extends Extension\AbstractExtension {

    public function getTokenParsers() {
        return array(
            new Tag\Register(),
            new Tag\Output(),
            new Tag\Img()
        );
    }

    public function getName() {
        return 'wave_view';
    }

}


?>