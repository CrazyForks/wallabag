<?php

namespace Wallabag\CoreBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Wallabag\CoreBundle\Entity\IgnoreOriginUserRule;
use Wallabag\CoreBundle\Entity\User;

class IgnoreOriginUserRuleFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $rule = new IgnoreOriginUserRule();
        $rule->setRule('host = "example.fr"');
        $rule->setConfig($this->getReference('admin-user', User::class)->getConfig());

        $manager->persist($rule);

        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            UserFixtures::class,
        ];
    }
}
