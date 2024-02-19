<?php

namespace Wallabag\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Wallabag\Entity\SiteCredential;
use Wallabag\Entity\User;
use Wallabag\Helper\CryptoProxy;

class SiteCredentialFixtures extends Fixture implements DependentFixtureInterface, ContainerAwareInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function setContainer(?ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function load(ObjectManager $manager): void
    {
        $credential = new SiteCredential($this->getReference('admin-user', User::class));
        $credential->setHost('.super.com');
        $credential->setUsername($this->container->get(CryptoProxy::class)->crypt('.super'));
        $credential->setPassword($this->container->get(CryptoProxy::class)->crypt('bar'));

        $manager->persist($credential);

        $credential = new SiteCredential($this->getReference('admin-user', User::class));
        $credential->setHost('paywall.example.com');
        $credential->setUsername($this->container->get(CryptoProxy::class)->crypt('paywall.example'));
        $credential->setPassword($this->container->get(CryptoProxy::class)->crypt('bar'));

        $manager->persist($credential);

        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            UserFixtures::class,
        ];
    }
}
