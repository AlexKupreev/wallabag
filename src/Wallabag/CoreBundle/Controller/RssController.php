<?php

namespace Wallabag\CoreBundle\Controller;

use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Exception\OutOfRangeCurrentPageException;
use Pagerfanta\Pagerfanta;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Wallabag\CoreBundle\Entity\Entry;
use Wallabag\UserBundle\Entity\User;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RssController extends Controller
{
    /**
     * Shows unread entries for current user.
     *
     * @Route("/feed/{username}/{token}/unread/{page}", name="unread_rss", defaults={"page": 1})
     * @Route("/{username}/{token}/unread.xml", defaults={"page": 1})
     * @ParamConverter("user", class="WallabagUserBundle:User", converter="username_rsstoken_converter")
     *
     * @param User $user
     * @param $page
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showUnreadAction(User $user, $page)
    {
        return $this->showEntries('unread', $user, $page);
    }

    /**
     * Shows read entries for current user.
     *
     * @Route("/feed/{username}/{token}/archive/{page}", name="archive_rss", defaults={"page": 1})
     * @Route("/{username}/{token}/archive.xml", defaults={"page": 1})
     * @ParamConverter("user", class="WallabagUserBundle:User", converter="username_rsstoken_converter")
     *
     * @param User $user
     * @param $page
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showArchiveAction(User $user, $page)
    {
        return $this->showEntries('archive', $user, $page);
    }

    /**
     * Shows starred entries for current user.
     *
     * @Route("/feed/{username}/{token}/starred/{page}", name="starred_rss", defaults={"page": 1})
     * @Route("/{username}/{token}/starred.xml", defaults={"page": 1})
     * @ParamConverter("user", class="WallabagUserBundle:User", converter="username_rsstoken_converter")
     *
     * @param User $user
     * @param $page
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showStarredAction(User $user, $page)
    {
        return $this->showEntries('starred', $user, $page);
    }

    /**
     * Global method to retrieve entries depending on the given type
     * It returns the response to be send.
     *
     * @param string $type Entries type: unread, starred or archive
     * @param User   $user
     * @param int    $page
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function showEntries($type, User $user, $page = 1)
    {
        $repository = $this->getDoctrine()->getRepository('WallabagCoreBundle:Entry');

        switch ($type) {
            case 'starred':
                $qb = $repository->getBuilderForStarredByUser($user->getId());
                break;

            case 'archive':
                $qb = $repository->getBuilderForArchiveByUser($user->getId());
                break;

            case 'unread':
                $qb = $repository->getBuilderForUnreadByUser($user->getId());
                break;

            default:
                throw new \InvalidArgumentException(sprintf('Type "%s" is not implemented.', $type));
        }

        $pagerAdapter = new DoctrineORMAdapter($qb->getQuery(), true, false);
        $entries = new Pagerfanta($pagerAdapter);

        $perPage = $user->getConfig()->getRssLimit() ?: $this->getParameter('wallabag_core.rss_limit');
        $entries->setMaxPerPage($perPage);

        $url = $this->generateUrl(
            $type.'_rss',
            [
                'username' => $user->getUsername(),
                'token' => $user->getConfig()->getRssToken(),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        try {
            $entries->setCurrentPage((int) $page);
        } catch (OutOfRangeCurrentPageException $e) {
            if ($page > 1) {
                return $this->redirect($url.'/'.$entries->getNbPages());
            }
        }

        return $this->render('@WallabagCore/themes/common/Entry/entries.xml.twig', [
            'type' => $type,
            'url' => $url,
            'entries' => $entries,
            'user' => $user->getUsername(),
            'domainName' => $this->getParameter('domain_name'),
            'version' => $this->getParameter('wallabag_core.version'),
        ]);
    }
}
