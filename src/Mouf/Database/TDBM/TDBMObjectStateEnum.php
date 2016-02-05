<?php

namespace Mouf\Database\TDBM;

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
    const STATE_DETACHED = 'detached';
    const STATE_NEW = 'new';
    const STATE_NOT_LOADED = 'not loaded';
    const STATE_LOADED = 'loaded';
    const STATE_DIRTY = 'dirty';
    const STATE_DELETED = 'deleted';
}
