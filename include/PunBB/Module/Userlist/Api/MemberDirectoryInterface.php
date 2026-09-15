<?php

declare(strict_types=1);

namespace PunBB\Module\Userlist\Api;

use PunBB\Module\Userlist\Api\Data\GroupInterface;
use PunBB\Module\Userlist\Api\Data\MemberInterface;
use PunBB\Module\Userlist\Api\Data\MemberSearchInterface;

/**
 * The registered members: every account but the guest and the unverified.
 */
interface MemberDirectoryInterface {
	public function count(MemberSearchInterface $search): int;

	/** @return list<MemberInterface> $limit members from $offset on, in the search's order, then by id */
	public function find(MemberSearchInterface $search, int $offset, int $limit): array;

	/** @return list<GroupInterface> every group a member can be in, by id */
	public function groups(): array;
}
