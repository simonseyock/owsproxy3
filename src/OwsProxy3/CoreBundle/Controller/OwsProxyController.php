<?php

namespace OwsProxy3\CoreBundle\Controller;

use Buzz\Message\MessageInterface;
use OwsProxy3\CoreBundle\Component\Utils;
use OwsProxy3\CoreBundle\Component\CommonProxy;
use OwsProxy3\CoreBundle\Component\Exception\HTTPStatus403Exception;
use OwsProxy3\CoreBundle\Component\Exception\HTTPStatus502Exception;
use OwsProxy3\CoreBundle\Component\ProxyQuery;
use OwsProxy3\CoreBundle\Component\WmsProxy;
use OwsProxy3\CoreBundle\Component\WfsProxy;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

//use OwsProxy3\CoreBundle\Component\Url;

/**
 * Description of OwsProxyController
 *
 * @author A.R.Pour
 * @author P. Schmidt
 */
class OwsProxyController extends Controller
{

    /**
     * Handles the client's request
     *
     * @param type $url the url
     * @param type $content the POST content
     * @return \Symfony\Component\HttpFoundation\Response the response
     */
    public function genericProxyAction($url, $content = null)
    {
        $request = $this->get('request');
        try
        {
            $proxy_config = $this->container->getParameter("owsproxy.proxy");
            $headers_req = Utils::getHeadersFromRequest($request);
            $getParams = Utils::getParams($request, Utils::$METHOD_GET);
            $postParams = Utils::getParams($request, Utils::$METHOD_POST);
            if(null === $content) {
                $content = $request->getContent();
            }
            $proxy_query = ProxyQuery::createFromUrl($url, null, null,
                                                     $headers_req, $getParams,
                                                     $postParams, $content);
            $proxy = new CommonProxy($proxy_config, $proxy_query);
            $response = new Response();
            $browserResponse = $proxy->handle();
            Utils::setHeadersFromBrowserResponse($response, $browserResponse);
//            $content_ = $browserResponse->getContent();
            $response->setContent($browserResponse->getContent());
            return $response;
        } catch(HTTPStatus403Exception $e)
        {
            return $this->exceptionImage($e, $request);
        } catch(HTTPStatus502Exception $e)
        {
            return $this->exceptionImage($e, $request);
        } catch(\Exception $e)
        {
            if($e->getCode() === 0) $e = new \Exception($e->getMessage(), 500);
            return $this->exceptionHtml($e);
        }
    }

    /**
     * Handles the client's request
     *
     * @Route("/")
     * @return \Symfony\Component\HttpFoundation\Response the response
     */
    public function entryPointAction()
    {
        $request = $this->get('request');
        $proxy_query = ProxyQuery::createFromRequest($request);
        $service = $proxy_query->getGetPostParamValue("service", true);
        // Switch proxy
        switch(strtoupper($service))
        {
            case 'WMS':
                try
                {
                    $dispatcher = $this->container->get('event_dispatcher');
                    $proxy_config = $this->container->getParameter("owsproxy.proxy");
                    $proxy_query = ProxyQuery::createFromRequest($request);
                    $proxy = new WmsProxy($dispatcher, $proxy_config, $proxy_query);
                    $browserResponse = $proxy->handle();

                    $response = new Response();
                    Utils::setHeadersFromBrowserResponse($response,
                                                         $browserResponse);
                    $response->setContent($browserResponse->getContent());
                    return $response;
                } catch(HTTPStatus403Exception $e)
                {
                    return $this->exceptionImage($e, $request);
                } catch(HTTPStatus502Exception $e)
                {
                    return $this->exceptionImage($e, $request);
                } catch(\Exception $e)
                {
                    if($e->getCode() === 0)
                            $e = new \Exception($e->getMessage(), 500);
                    return $this->exceptionHtml($e);
                }
            case 'WFS':
                try {
                    $dispatcher = $this->container->get('event_dispatcher');
                    $proxy_config = $this->container->getParameter("owsproxy.proxy");
                    $proxy_query = ProxyQuery::createFromRequest($request);
                    $proxy = new WfsProxy($dispatcher, $proxy_config, $proxy_query);
                    $browserResponse = $proxy->handle();

                    $response = new Response();
                    Utils::setHeadersFromBrowserResponse($response, $browserResponse);
                    $response->setContent($browserResponse->getContent());
                    return $response;
                } catch(\RuntimeException $e) {
                    return $this->exceptionHtml(new \Exception($e->getMessage(), 500));
                }
            default: //@TODO ?
                return $this->exceptionHtml(new \Exception('Unknown Service Type', 404));
        }
    }

    /**
     * Creates a response with an exception as HTML
     *
     * @param \Exception $e the exception
     * @return \Symfony\Component\HttpFoundation\Response the response
     */
    private function exceptionHtml(\Exception $e)
    {
        $response = new Response();
        $html = $this->render("OwsProxy3CoreBundle::exception.html.twig",
                              array("exception" => $e));
        $response->headers->set('Content-Type', 'text/html');
        $response->setStatusCode($e->getCode());
        $response->setContent($html->getContent());
        return $response;
    }

    /**
     * Creates a response with an exception as png image
     *
     * @param \Exception $e the exception
     * @param Request $request the request
     * @return \Symfony\Component\HttpFoundation\Response the response
     */
    private function exceptionImage(\Exception $e, $request)
    {
        $format = Utils::getParamValueFromAll($request, "format", true);
        $w = Utils::getParamValueFromAll($request, "width", true);
        $h = Utils::getParamValueFromAll($request, "height", true);
        if($format === null || $w === null || $h === null
                || !is_int(strpos(strtolower($format), "image"))
                || intval($w) === 0 || intval($h) === 0)
        {
            return $this->exceptionHtml($e);
        }
        $image = new \Imagick();
        $draw = new \ImagickDraw();
        $pixel = new \ImagickPixel('none');

        $image->newImage(intval($w), intval($h), $pixel);

        $draw->setFillColor('grey');
        $draw->setFontSize(30);
        $st_x = 200;
        $st_y = 200;
        $ang = -45;
        for($x = 10; $x < $w; $x += $st_x)
        {
            for($y = 10; $y < $h; $y += $st_y)
            {
                $image->annotateImage($draw, $x, $y, $ang, $e->getMessage());
            }
        }

        $image->setImageFormat('png');

        $response = new Response();
        $response->headers->set('Content-Type', "image/png");
        $response->setStatusCode($e->getCode());
        $response->setContent($image->getimageblob());

        return $response;
    }

}
