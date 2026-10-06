<?php

// Supply your own callbacks before marking routes as protected.
return ['check' => fn() => current_user() !== null, 'can' => fn($permission) => can($permission), 'login' => 'login'];
