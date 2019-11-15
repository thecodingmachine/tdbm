<?php
declare(strict_types=1);

namespace TheCodingMachine\TDBM;

/*
 Copyright (C) 2006-20015 David Négrier - THE CODING MACHINE

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * Class containing all possible states for an object.
 *
 * @author David Negrier
 */
final class TDBMObjectStateEnum
{
    public const STATE_DETACHED = 'detached';
    public const STATE_NEW = 'new';
    public const STATE_SAVING = 'saving';
    public const STATE_NOT_LOADED = 'not loaded';
    public const STATE_LOADED = 'loaded';
    public const STATE_DIRTY = 'dirty';
    public const STATE_DELETED = 'deleted';
    public const STATE_PARTIALLY_LOADED = 'partially_loaded';
}
