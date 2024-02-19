<?php

namespace Wallabag\Controller;

use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Doctrine\ORM\QueryAdapter as DoctrineORMAdapter;
use Pagerfanta\Exception\OutOfRangeCurrentPageException;
use Pagerfanta\Pagerfanta;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wallabag\Entity\Tag;
use Wallabag\Entity\User;
use Wallabag\Helper\PreparePagerForEntries;
use Wallabag\Repository\EntryRepository;

class FeedController extends AbstractController
{
    private EntryRepository $entryRepository;

    public function __construct(EntryRepository $entryRepository)
    {
        $this->entryRepository = $entryRepository;
    }

    /**
     * Shows unread entries for current user.
     *
     * @Route("/feed/{username}/{token}/unread/{page}", name="unread_feed", defaults={"page"=1, "_format"="xml"})
     *
     * @ParamConverter("user", class="Wallabag\Entity\User", converter="username_feed_token_converter")
     *
     * @return Response
     */
    public function showUnreadFeedAction(User $user, $page)
    {
        return $this->showEntries('unread', $user, $page);
    }

    /**
     * Shows read entries for current user.
     *
     * @Route("/feed/{username}/{token}/archive/{page}", name="archive_feed", defaults={"page"=1, "_format"="xml"})
     *
     * @ParamConverter("user", class="Wallabag\Entity\User", converter="username_feed_token_converter")
     *
     * @return Response
     */
    public function showArchiveFeedAction(User $user, $page)
    {
        return $this->showEntries('archive', $user, $page);
    }

    /**
     * Shows starred entries for current user.
     *
     * @Route("/feed/{username}/{token}/starred/{page}", name="starred_feed", defaults={"page"=1, "_format"="xml"})
     *
     * @ParamConverter("user", class="Wallabag\Entity\User", converter="username_feed_token_converter")
     *
     * @return Response
     */
    public function showStarredFeedAction(User $user, $page)
    {
        return $this->showEntries('starred', $user, $page);
    }

    /**
     * Shows all entries for current user.
     *
     * @Route("/feed/{username}/{token}/all/{page}", name="all_feed", defaults={"page"=1, "_format"="xml"})
     *
     * @ParamConverter("user", class="Wallabag\Entity\User", converter="username_feed_token_converter")
     *
     * @return Response
     */
    public function showAllFeedAction(User $user, $page)
    {
        return $this->showEntries('all', $user, $page);
    }

    /**
     * Shows entries associated to a tag for current user.
     *
     * @Route("/feed/{username}/{token}/tags/{slug}/{page}", name="tag_feed", defaults={"page"=1, "_format"="xml"})
     *
     * @ParamConverter("user", class="Wallabag\Entity\User", converter="username_feed_token_converter")
     * @ParamConverter("tag", options={"mapping": {"slug": "slug"}})
     *
     * @return Response
     */
    public function showTagsFeedAction(Request $request, User $user, Tag $tag, PreparePagerForEntries $preparePagerForEntries, $page)
    {
        $sort = $request->query->get('sort', 'created');

        $sorts = [
            'created' => 'createdAt',
            'updated' => 'updatedAt',
        ];

        if (!isset($sorts[$sort])) {
            throw new BadRequestHttpException(sprintf('Sort "%s" is not available.', $sort));
        }

        $url = $this->generateUrl(
            'tag_feed',
            [
                'username' => $user->getUsername(),
                'token' => $user->getConfig()->getFeedToken(),
                'slug' => $tag->getSlug(),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $entriesByTag = $this->entryRepository->findAllByTagId(
            $user->getId(),
            $tag->getId(),
            $sorts[$sort]
        );

        $pagerAdapter = new ArrayAdapter($entriesByTag);

        $entries = $preparePagerForEntries->prepare(
            $pagerAdapter,
            $user
        );

        $perPage = $user->getConfig()->getFeedLimit() ?: $this->getParameter('wallabag_core.feed_limit');
        $entries->setMaxPerPage($perPage);

        if (null === $entries) {
            throw $this->createNotFoundException('No entries found?');
        }

        try {
            $entries->setCurrentPage($page);
        } catch (OutOfRangeCurrentPageException $e) {
            if ($page > 1) {
                return $this->redirect($url . '?page=' . $entries->getNbPages(), 302);
            }
        }

        return $this->render(
            'Entry/entries.xml.twig',
            [
                'type' => 'tag',
                'url' => $url,
                'entries' => $entries,
                'user' => $user->getUsername(),
                'domainName' => $this->getParameter('domain_name'),
                'version' => $this->getParameter('wallabag_core.version'),
                'tag' => $tag->getSlug(),
                'updated' => $this->prepareFeedUpdatedDate($entries, $sort),
            ],
            new Response('', 200, ['Content-Type' => 'application/atom+xml'])
        );
    }

    private function prepareFeedUpdatedDate(Pagerfanta $entries, $sort = 'created')
    {
        $currentPageResults = $entries->getCurrentPageResults();

        if (isset($currentPageResults[0])) {
            $firstEntry = $currentPageResults[0];
            if ('created' === $sort) {
                return $firstEntry->getCreatedAt();
            }

            return $firstEntry->getUpdatedAt();
        }

        return null;
    }

    /**
     * Global method to retrieve entries depending on the given type
     * It returns the response to be send.
     *
     * @param string $type Entries type: unread, starred or archive
     * @param int    $page
     *
     * @return Response
     */
    private function showEntries(string $type, User $user, $page = 1)
    {
        switch ($type) {
            case 'starred':
                $qb = $this->entryRepository->getBuilderForStarredByUser($user->getId());
                break;
            case 'archive':
                $qb = $this->entryRepository->getBuilderForArchiveByUser($user->getId());
                break;
            case 'unread':
                $qb = $this->entryRepository->getBuilderForUnreadByUser($user->getId());
                break;
            case 'all':
                $qb = $this->entryRepository->getBuilderForAllByUser($user->getId());
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Type "%s" is not implemented.', $type));
        }

        $pagerAdapter = new DoctrineORMAdapter($qb->getQuery(), true, false);
        $entries = new Pagerfanta($pagerAdapter);

        $perPage = $user->getConfig()->getFeedLimit() ?: $this->getParameter('wallabag_core.feed_limit');
        $entries->setMaxPerPage($perPage);

        $url = $this->generateUrl(
            $type . '_feed',
            [
                'username' => $user->getUsername(),
                'token' => $user->getConfig()->getFeedToken(),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        try {
            $entries->setCurrentPage((int) $page);
        } catch (OutOfRangeCurrentPageException $e) {
            if ($page > 1) {
                return $this->redirect($url . '/' . $entries->getNbPages());
            }
        }

        return $this->render('Entry/entries.xml.twig', [
            'type' => $type,
            'url' => $url,
            'entries' => $entries,
            'user' => $user->getUsername(),
            'domainName' => $this->getParameter('domain_name'),
            'version' => $this->getParameter('wallabag_core.version'),
            'updated' => $this->prepareFeedUpdatedDate($entries),
        ], new Response('', 200, ['Content-Type' => 'application/atom+xml']));
    }
}
