<?php

namespace Netgen\Bundle\EzSyliusBundle\Authentication;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Repository;
use eZ\Publish\API\Repository\Values\User\User;
use eZ\Publish\Core\MVC\Symfony\Security\UserInterface as EzUserInterface;
use Ibexa\Core\MVC\Symfony\Security\Authentication\RepositoryAuthenticationProvider as BaseAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

class DaoAuthenticationProvider extends BaseAuthenticationProvider
{

    private $permissionResolver;

    /**
     * {@inheritdoc}
     */
    protected function checkAuthentication(UserInterface $user, UsernamePasswordToken $token)
    {
        parent::checkAuthentication($user, $token);

        if ($user instanceof EzUserInterface) {
            $apiUser = $user->getAPIUser();

            if ($apiUser instanceof User) {
                $this->permissionResolver->setCurrentUserReference(
                    $apiUser
                );
            }
        }
    }
}
