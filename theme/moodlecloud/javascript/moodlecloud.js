(function() {

    var checkFunction = function() {
        var div = document.getElementById('moodlecloud_ad');
        if (div && div.clientHeight === 0) {
            div.innerHTML = 'ad block detected';
        }
    };

    checkFunction();
}());
