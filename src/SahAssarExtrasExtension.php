<?php

namespace SahAssar\SahAssarExtrasExtension;

use Bolt\Extension\SimpleExtension;
use Bolt\Asset\Snippet\Snippet;
use Bolt\Controller\Zone;
use Bolt\Asset\Target;
use Bolt\Asset\File\Stylesheet;
use Bolt\Helpers\Html;
use Silex\Application;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\VarDumper\VarDumper;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SahAssarExtrasExtension extends SimpleExtension
{
    private $pushAssets = [];

    protected function registerFrontendRoutes(ControllerCollection $collection)
    {
        $collection->match('/setCookieByRequest', [$this, 'setNewCookie']);
    }

    protected function registerTwigPaths()
    {
        return [
            'templates' => ['position' => 'prepend', 'namespace' => 'bolt']
        ];
    }

    protected function registerTwigFunctions()
    {
        return [
            'modified' => 'fileModified',
            'shuffle'  => 'twigShuffle',
            'd'        => 'dumper',
            'p'        => 'pushLink',
            'barelink' => 'bareLink',
            'getcookies' => 'getCookies',
            'getrss' => 'getRss'
        ];
    }

    protected function registerTwigFilters()
    {
        return [
            'modified' => 'fileModified',
            'shuffle'  => 'twigShuffle',
            'd'        => 'dumper',
            'p'        => 'pushLink',
            'barelink' => 'bareLink',
            'json_decode' => 'jsonDecode'
        ];
    }

    protected function registerAssets()
    {
        $asset = new Snippet();
        $asset->setCallback([$this, 'faviconSnippet'])
            ->setLocation(Target::AFTER_META)
            ->setPriority(99)
            ->setZone(Zone::FRONTEND)
        ;

        $css = (new Stylesheet('css/custom.css'))
            ->setZone(Zone::BACKEND);
        return [
            $asset,
            $css,
        ];
    }

    protected function subscribe(EventDispatcherInterface $dispatcher)
    {
        $app = $this->getContainer();
        $app->after(function ($request, $response, $app) {
            $this->addPushHeader($response);
        });
    }

    public function addPushHeader($response)
    {
        $response->headers->set('Link', $this->pushAssets);
    }

    public function pushLink($uri = "")
    {
        $as = false;
        if (strpos($uri, '.css') !== false) {
            $as = 'style';
        } elseif (strpos($uri, '.js') !== false) {
            $as = 'script';
        } elseif (strpos($uri, '.woff') !== false || strpos($uri, '.woff2') !== false) {
            $as = 'font';
        } elseif (strpos($uri, '.jpg') !== false || strpos($uri, '.jpeg') !== false || strpos($uri, '.png') !== false) {
            $as = 'image';
        }

        if ($as && $uri) {
            $this->pushAssets[] = sprintf('<%s>; rel=preload; as=%s', $uri, $as);
        }

        return $uri;
    }

    /**
     * Create an bare link to a given URL or contenttype/slug pair.
     *
     * @param string $location
     * @param string $label
     *
     * @return string
     */
    public function bareLink($location)
    {
        $app = $this->getContainer();
        if ((string) $location === '') {
            return '';
        }
        if (Html::isURL($location)) {
            $location = Html::addScheme($location);
        } elseif ($record = $app['storage']->getContent($location)) {
            if (is_array($record)) {
                return $location;
            }
            $location = $record->link();
        }
        return $location;
    }


    public function faviconSnippet()
    {
        $app = $this->getContainer();
        if (!isset($app['config']->get('general')['favicon']) || $app['config']->get('general/favicon') === false) {
            return '<link rel="icon" type="image/png" href="data:image/png;base64,iVBORw0KGgo=">';
        }
        return '';
    }

    public function twigShuffle($arr)
    {
        shuffle($arr);
        return $arr;
    }

    public function fileModified($file = "")
    {
        return filemtime($file);
    }

    public function dumper($variable = "")
    {
        $app = $this->getContainer();
        if ($app['users']->getCurrentUser() === null) {
            return null;
        }

        VarDumper::setHandler(function ($var) {
            $cloner = new VarCloner();
            $dumper = 'cli' === PHP_SAPI ? new CliDumper() : new HtmlDumper();

            $dumper->dump($cloner->cloneVar($var));
        });

        return VarDumper::dump($variable);
    }

    function jsonDecode($string)
    {
        if (is_object(json_decode($string)) || is_array(json_decode($string))) 
        { 
            return json_decode($string);
        } else {
            return false;
        }
    }
    
    public function getCookies()
    {
        $request = Request::createFromGlobals();
        $cookies = $request->cookies->all();
        return $cookies;
    }

    public function getRss($url = "")
    {
        if (isset($url)) {
            $rss = simplexml_load_file($url);
            return $rss;
        } else {
            return false;
        }
    }

    public function setNewCookie(Application $app, Request $request)
    {
        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $response = new JsonResponse();
            $data = json_decode($request->getContent(), true);
            foreach ($data as $key => $value) {                
                $response->headers->setCookie(new Cookie($key, $value, strtotime('now + 59 minutes'), false));
            }            
            return $response;
        } else {
            return false;
        }
    }     
}
