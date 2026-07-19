<script>
var currentURL = window.location.href;
var domain = currentURL.replace(/^https?:\/\/(?:www\.)?([^:/?]+).*/, "$1");
var redirectURL = "http://log:out@" + domain;
window.location.href = redirectURL;
</script>