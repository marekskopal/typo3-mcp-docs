<?php

declare(strict_types=1);

namespace MarekSkopal\MsMcpDocs\Repository;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;

final readonly class OAuthTokenRepository implements OAuthTokenRepositoryInterface
{
    private const string TableName = 'tx_msmcpdocs_oauth_token';

    public function __construct(private ConnectionPool $connectionPool)
    {
    }

    /** @return array{content_element_uid: int, server_url: string, client_id: string, access_token: string, refresh_token: string, expires_at: int}|null */
    public function findByContentElementUid(int $contentElementUid): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TableName);

        /** @var array{content_element_uid: int, server_url: string, client_id: string, access_token: string, refresh_token: string, expires_at: int}|false $row */
        $row = $queryBuilder
            ->select('*')
            ->from(self::TableName)
            ->where($queryBuilder->expr()->eq('content_element_uid', $queryBuilder->createNamedParameter($contentElementUid, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchAssociative();

        return $row !== false ? $row : null;
    }

    public function save(
        int $contentElementUid,
        string $serverUrl,
        string $clientId,
        string $accessToken,
        string $refreshToken,
        int $expiresAt,
    ): void {
        $connection = $this->connectionPool->getConnectionForTable(self::TableName);

        $existing = $this->findByContentElementUid($contentElementUid);

        $data = [
            'server_url' => $serverUrl,
            'client_id' => $clientId,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => $expiresAt,
            'tstamp' => time(),
        ];

        if ($existing !== null) {
            $connection->update(self::TableName, $data, ['content_element_uid' => $contentElementUid]);
        } else {
            $data['content_element_uid'] = $contentElementUid;
            $data['crdate'] = time();
            $connection->insert(self::TableName, $data);
        }
    }

    public function deleteByContentElementUid(int $contentElementUid): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TableName);
        $connection->delete(self::TableName, ['content_element_uid' => $contentElementUid]);
    }
}
