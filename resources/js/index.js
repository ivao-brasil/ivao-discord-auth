var images = [
    'https://wallpapersite.com/images/pages/pic_w/1063.jpg',
    'http://www.hdwallpaper.nu/wp-content/uploads/2017/04/PLAYERUNKNOWNS-BATTLEGROUNDS-12937710.jpg',
    'https://www.hdwallpapers.in/walls/overwatch_4k-HD.jpg', 'https://images.alphacoders.com/186/186993.jpg',
    'https://images4.alphacoders.com/602/thumb-1920-602788.png'];

$('#container').append('<style>#container, .acceptContainer:before, .logoContainer:before {background: url(' + images[Math.floor(Math.random() * images.length)] + ') center fixed }');

