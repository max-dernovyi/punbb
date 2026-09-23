<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Patch;

/**
 * A module that changes its data between releases, declaring the data patches
 * on its module class. A board looks for a module's patches only while its
 * data version is behind the module's, so a patch comes with a version raised.
 */
interface PatchOwnerInterface {
	/** @return list<PatchDeclaration> in the order they apply, where no dependency orders them */
	public function patches(): array;
}
