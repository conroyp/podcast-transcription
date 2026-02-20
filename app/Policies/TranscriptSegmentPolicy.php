<?php

namespace App\Policies;

use App\Models\TranscriptSegment;
use App\Models\User;

class TranscriptSegmentPolicy
{
    /**
     * Determine whether the user can update the model.
     *
     * Any authenticated user may update transcript segments, as the application
     * is restricted to local/testing environments and all authenticated users
     * are trusted administrators.
     */
    public function update(User $user, TranscriptSegment $transcriptSegment): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Any authenticated user may delete transcript segments, as the application
     * is restricted to local/testing environments and all authenticated users
     * are trusted administrators.
     */
    public function delete(User $user, TranscriptSegment $transcriptSegment): bool
    {
        return true;
    }
}
