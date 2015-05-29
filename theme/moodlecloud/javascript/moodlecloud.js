function detectAdblock() {
    var div = document.getElementById('moodlefree_ad');
    if (div && div.clientHeight == 0) {
        div.innerHTML = 'ad block detected';
    }
}

detectAdblock();
