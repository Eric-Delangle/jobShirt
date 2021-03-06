<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Controller\Admincontroller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Sylius\Component\Core\Dashboard\SalesDataProviderInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Dashboard\DashboardStatisticsProviderInterface;

final class DashboardController 
{
    /** @var DashboardStatisticsProviderInterface */
    private $statisticsProvider;

    /** @var ChannelRepositoryInterface */
    private $channelRepository;

    /** @var EngineInterface */
    private $templatingEngine;

    /** @var RouterInterface */
    private $router;

    /** @var SalesDataProviderInterface|null */
    private $salesDataProvider;

    public function __construct(
        DashboardStatisticsProviderInterface $statisticsProvider,
        ChannelRepositoryInterface $channelRepository,
        EngineInterface $templatingEngine,
        RouterInterface $router,
        ?SalesDataProviderInterface $salesDataProvider = null
    ) {
        $this->statisticsProvider = $statisticsProvider;
        $this->channelRepository = $channelRepository;
        $this->templatingEngine = $templatingEngine;
        $this->router = $router;
        $this->salesDataProvider = $salesDataProvider;

        if ($this->salesDataProvider === null) {
            @trigger_error(
                sprintf('Not passing a $salesDataProvider to %s constructor is deprecated since Sylius 1.7 and will be removed in Sylius 2.0.', self::class),
                \E_USER_DEPRECATED
            );
        }
    }

    public function indexAction(Request $request): Response
    {
        $channelCode = $request->query->get('channel');
dump($channelCode);
        /** @var ChannelInterface|null $channel */
        $channel = $this->findChannelByCodeOrFindFirst($channelCode);

        if (null === $channel) {
            return new RedirectResponse($this->router->generate('sylius_admin_channel_create'));
        }

        $statistics = $this->statisticsProvider->getStatisticsForChannel($channel);
        $data = ['statistics' => $statistics, 'channel' => $channel];

        if ($this->salesDataProvider !== null) {
            $data['sales_summary'] = $this->salesDataProvider->getLastYearSalesSummary($channel);
            $data['currency'] = $channel->getBaseCurrency()->getCode();
        }

        return $this->templatingEngine->renderResponse('@App/Controller/Admincontroller/Dashboard/index.html.twig', $data);
    }

    private function findChannelByCodeOrFindFirst(?string $channelCode): ?ChannelInterface
    {
        $channel = null;
        if (null !== $channelCode) {
            $channel = $this->channelRepository->findOneByCode($channelCode);
        }

        if (null === $channel) {
            $channels = $this->channelRepository->findAll();

            $channel = current($channels) === false ? null : current($channels);
        }

        return $channel;
    }
}
