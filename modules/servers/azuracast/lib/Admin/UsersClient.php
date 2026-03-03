<?php
declare(strict_types=1);

namespace WHMCS\Module\Server\AzuraCast\Admin;

use WHMCS\Module\Server\AzuraCast\AbstractClient;
use WHMCS\Module\Server\AzuraCast\Dto;
use WHMCS\Module\Server\AzuraCast\Dto\ApiKeyDto;
use WHMCS\Module\Server\AzuraCast\Dto\RoleDto;
use WHMCS\Module\Server\AzuraCast\Dto\UserDto;
use WHMCS\Module\Server\AzuraCast\Exception;
use WHMCS\Module\Server\AzuraCast\Exception\AccessDeniedException;
use WHMCS\Module\Server\AzuraCast\Exception\ClientRequestException;

class UsersClient extends AbstractClient
{
    /**
     * @return Dto\UserDto[]
     *
     * @throws Exception\AccessDeniedException
     * @throws Exception\ClientRequestException
     */
    public function list(): array
    {
        $usersData = $this->request('GET', 'admin/users');

        $users = [];
        foreach ($usersData as $userData) {
            $users[] = Dto\UserDto::fromArray($userData);
        }
        return $users;
    }

    /**
     * @return ?Dto\UserDto
     *
     * @throws Exception\AccessDeniedException
     * @throws Exception\ClientRequestException
     */
    public function searchByEmail($email): ?Dto\UserDto
    {
        $usersData = $this->request('GET', 'admin/users');

        foreach ($usersData as $userData) {
            if ($userData['email'] === $email) {
                return Dto\UserDto::fromArray($userData);
            }
        }
        return null;
    }

    /**
     * @param int $userId
     *
     * @return Dto\UserDto
     *
     * @throws Exception\AccessDeniedException
     * @throws Exception\ClientRequestException
     */
    public function get(int $userId): Dto\UserDto
    {
        $userData = $this->request(
            'GET',
            sprintf('admin/user/%s', $userId)
        );

        return Dto\UserDto::fromArray($userData);
    }

    /**
     * @param string $email
     * @param string $newPassword
     * @param string $name
     * @param string $locale
     * @param string $theme
     * @param RoleDto[] $roles
     * @param ApiKeyDto[] $apiKeys
     *
     * @return Dto\UserDto
     *
     * @throws Exception\AccessDeniedException
     * @throws Exception\ClientRequestException
     */
    public function create(
        string $email,
        string $newPassword,
        string $name,
        string $locale,
        array $roles
    ): Dto\UserDto {
        $userDto = new Dto\UserDto(
            0,
            $email,
            $name,
            $locale,
            time(),
            time(),
            $roles
        );
        $userDto->setNewPassword($newPassword);

        $userData = $this->request(
            'POST',
            'admin/users',
            ['json' => $userDto]
        );

        return Dto\UserDto::fromArray($userData);
    }

    /**
     * @param int $userId
     * @param string $email
     * @param string $newPassword
     * @param string $name
     * @param string $locale
     * @param RoleDto[] $roles
     * @param int $createdAt
     * @return UserDto
     *
     * @throws AccessDeniedException
     * @throws ClientRequestException
     */
    public function update(
        int $userId,
        string $email,
        string $newPassword,
        string $name,
        string $locale,
        array $roles,
        int $createdAt
    ): Dto\UserDto {

        $userDto = new Dto\UserDto(
            $userId,
            $email,
            $name,
            $locale,
            $createdAt,
            time(),
            $roles
        );

        if ($newPassword !== '') {
            $userDto->setNewPassword($newPassword);
        }

        $this->request(
            'PUT',
            sprintf('admin/user/%s', $userId),
            ['json' => $userDto]
        );

        $userDto->setNewPassword('');

        return $userDto;
    }

    /**
     * @param int $userId
     *
     * @return void
     *
     * @throws Exception\AccessDeniedException
     * @throws Exception\ClientRequestException
     */
    public function delete(int $userId): void
    {
        $this->request(
            'DELETE',
            sprintf('admin/user/%s', $userId)
        );
    }

    public function getAdministratorUserIdFromToken() : int
    {
        $administratorUser = $this->request('GET', 'frontend/account/me');
        if (!isset($administratorUser['id'])) {
            throw new ClientRequestException('Administrator user ID not found in response');
        }

        return $administratorUser['id'];
    }


    /**
     * @param int $userId
     *
     * @return string
     *
     * @throws Exception\AccessDeniedException
     * @throws Exception\ClientRequestException
     */
    public function getLoginLink(int $userId): string
    {
        $loginLinkRequestData = [
            "user" => $userId,
            "type" => "login",
            "comment" => "WHMCS SSO Login",
            "expires_minutes" => 10
        ];

        $loginLinkResponseData = $this->request(
            'POST',
            sprintf('admin/login_tokens'),
            ['json' => $loginLinkRequestData]
        );

        /* RESPONSE FORMAT:

            "success": true,
            "message": "Record created successfully.",
            "formatted_message": "Record created successfully.",
            "record": {
                "user": {
                "email": "",
                "auth_password": "",
                "name": "Customer",
                "locale": "en_US",
                "show_24_hour_time": null,
                "two_factor_secret": null,
                "created_at": 1766327680,
                "updated_at": 1766327680,
                "roles": [
                    {
                    "name": "Station 3 Role",
                    "id": 2
                    }
                ],
                "api_keys": [],
                "passkeys": [],
                "login_tokens": [
                    "xxxxxx"
                ],
                "id": 2
                },
                "type": "login",
                "comment": "SSO Login",
                "created_at": 1766328498,
                "expires_at": 1766328798,
                "id": "xxxxxx"
            },
            "links": {
                "self": "https://..../api/admin/login_token/7c56de5c0457954f",
                "login": "https://..../login-token/7c56de5c0457954f:653635cdd176a9a67bb67ec4443252f2"
            }
            }
        */

        if (!isset($loginLinkResponseData['links']['login'])) {
            throw new ClientRequestException('Login link not found in response');
        }

        return $loginLinkResponseData['links']['login'];
    }
}
