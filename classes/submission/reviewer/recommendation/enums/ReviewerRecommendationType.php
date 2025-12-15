<?php

namespace PKP\submission\reviewer\recommendation\enums;

enum ReviewerRecommendationType: int
{
    case APPROVED = 1;
    case NOT_APPROVED = 2;
    case REVISIONS_REQUESTED = 3;
    case WITH_COMMENTS = 4;
}
