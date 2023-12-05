<?php

namespace Netgen\Bundle\EzSyliusBundle\Provider;

use Doctrine\ORM\EntityRepository;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\Values\User\User;
use eZ\Publish\Core\MVC\Symfony\Security\UserInterface as EzUserInterface;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Core\Repository\Values\User\UserReference;
use Netgen\Bundle\EzSyliusBundle\Entity\EzSyliusUser;
use Sylius\Bundle\UserBundle\Provider\UserProviderInterface as SyliusUserProviderInterface;
use Sylius\Component\User\Model\UserInterface as SyliusUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class EmailOrNameBased implements UserProviderInterface
{
    /**
     * @var \Sylius\Bundle\UserBundle\Provider\UserProviderInterface
     */
    protected $innerUserProvider;

    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    protected $eZUserRepository;

    /**
     * @var \Sylius\Component\User\Repository\UserRepositoryInterface
     */
    protected $syliusUserRepository;

    /**
     * @var \eZ\Publish\API\Repository\Repository
     */
    protected $repository;

    protected PermissionResolver $permissionResolver;
    
    /**
     * @var string
     */
    protected $syliusUserType;

    /**
     * Constructor.
     *
     * @param \Sylius\Bundle\UserBundle\Provider\UserProviderInterface $innerUserProvider
     * @param \Doctrine\ORM\EntityRepository $eZUserRepository
     * @param \Sylius\Component\User\Repository\UserRepositoryInterface $syliusUserRepository
     * @param \eZ\Publish\API\Repository\Repository $repository
     * @param string $syliusUserType
     */
    public function __construct(
        SyliusUserProviderInterface $innerUserProvider,
        EntityRepository $eZUserRepository,
        UserRepositoryInterface $syliusUserRepository,
        Repository $repository,
        PermissionResolver $permissionResolver,
        $syliusUserType
    ) {
        $this->innerUserProvider = $innerUserProvider;
        $this->eZUserRepository = $eZUserRepository;
        $this->syliusUserRepository = $syliusUserRepository;
        $this->repository = $repository;
        $this->permissionResolver = $permissionResolver;
        $this->syliusUserType = $syliusUserType;
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByUsername($usernameOrEmail)
    {
        $user = $this->innerUserProvider->loadUserByUsername($usernameOrEmail);

        $apiUser = $this->loadAPIUser($user);

        if ($user instanceof EzUserInterface && $apiUser instanceof User) {
            $user->setAPIUser($apiUser);
        }

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function refreshUser(UserInterface $user)
    {
        $user = $this->innerUserProvider->refreshUser($user);

        $apiUser = $this->loadAPIUser($user);

        if ($user instanceof EzUserInterface && $apiUser instanceof User) {
            $user->setAPIUser($apiUser);

            $this->permissionResolver->setCurrentUserReference(
                $apiUser
            );
        }

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass($class)
    {
        return $this->innerUserProvider->supportsClass($class);
    }

    /**
     * Loads Sylius user based on provided eZ API user.
     *
     * @param \eZ\Publish\API\Repository\Values\User\User $apiUser
     *
     * @return \Sylius\Component\User\Model\UserInterface
     */
    public function loadUserByAPIUser(User $apiUser)
    {
        $eZSyliusUser = $this->eZUserRepository->findOneBy(
            array(
                'eZUserId' => $apiUser->getUserId(),
                'syliusUserType' => $this->syliusUserType,
            )
        );

        if (!$eZSyliusUser instanceof EzSyliusUser) {
            return null;
        }

        return $this->syliusUserRepository->find(
            $eZSyliusUser->getSyliusUserId()
        );
    }

    /**
     * Loads eZ API user based on provided Sylius user.
     *
     * @param \Sylius\Component\User\Model\UserInterface $user
     *
     * @return \eZ\Publish\API\Repository\Values\User\User
     */
    protected function loadAPIUser(SyliusUserInterface $user)
    {
        $eZSyliusUser = $this->eZUserRepository->findOneBy(
            array(
                'syliusUserId' => $user->getId(),
                'syliusUserType' => $this->syliusUserType,
            )
        );

        if (!$eZSyliusUser instanceof EzSyliusUser) {
            return null;
        }

        try {
            return $this->repository->getUserService()->loadUser(
                $eZSyliusUser->getEzUserId()
            );
        } catch (NotFoundException $e) {
            return null;
        }
    }
}
