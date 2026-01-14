<?php

switch ($op) {
    case 'requestAuthorResponse':
        return new PKP\pages\reviewResponse\ReviewResponseHandler();
}
