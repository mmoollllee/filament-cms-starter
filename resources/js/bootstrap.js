// Spam-protected mail/phone link decryption — shipped by the vendor package
// (yannkuesthardt/laravel-spamprotect); bundled from there instead of a hand-maintained copy.
import "../../vendor/yannkuesthardt/laravel-spamprotect/resources/js/spamprotect.js";

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
