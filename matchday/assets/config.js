/* HP Matchday · settings that can change without touching the app code. */
window.MD_CONFIG = {
  // Official club crest. Leave empty until an approved file is supplied, then set e.g. 'assets/crest.png'.
  // It is shown in the header and on the card only; never on the jersey.
  crest: '',

  // Game length in seconds, and how many games one player may play (their best score counts).
  gameSeconds: 60,
  maxGames: 2,

  // Seconds without a touch or key press before a station returns to its first screen.
  idleSeconds: 150,

  // Match sounds. Add the client's files under assets/audio/ and name them here, e.g.
  //   sounds: { music: 'assets/audio/stadium.mp3', kick: 'assets/audio/kick.mp3', goal: 'assets/audio/goal.mp3',
  //             save: 'assets/audio/save.mp3', miss: 'assets/audio/miss.mp3', whistle: 'assets/audio/whistle.mp3' }
  // `music` loops for the whole journey; the others play once on that moment. Empty = silent.
  sounds: {},

  // What each station shows off, on its closing screen (copy from the HP experience-zone deck).
  products: {
    1: {
      head: 'How do you like your player picture?', prod: 'HP OmniBook 5', icon: 'cam',
      heroTitle: '5MP IR CAMERA WITH AUTO-SWITCH HDR',
      heroText: 'Look great in every shot! Enjoy effortless, secure face unlock with Windows.',
      feat: [['UP TO 2K OLED DISPLAY', 'Rich, accurate colours with 100% DCI-P3.'],
             ['ULTRA-LIGHT DESIGN', 'Under 1.5 kg and just 1.5 cm thin.'],
             ['LONG-LASTING BATTERY', '20+ hours of battery life. Get 50% charge in just 30 minutes.']],
      nextHead: 'Ready to design your matchday jersey?', nextCta: 'Head to Station 02'
    },
    2: {
      head: 'Did your jersey come out the way you pictured it?', prod: 'HP OmniBook X', icon: 'screen',
      heroTitle: 'UP TO 3K OLED DISPLAY',
      heroText: 'Experience your creation come alive with 100% DCI-P3 and 500 nits brightness.',
      feat: [['UP TO 20 HOURS BATTERY', 'Shoot, edit and create without searching for a plug.'],
             ['SEAMLESS CONNECTIVITY', 'USB-A, HDMI and dual-sided USB-C.'],
             ['PREMIUM METAL DESIGN', '3-piece metal body with dual-anodised finish.']],
      nextHead: 'Ready to step up to the penalty spot?', nextCta: 'Head to Station 03'
    },
    3: {
      head: 'How fast were your reflexes?', prod: 'HP Victus 15', icon: 'bolt',
      heroTitle: '144Hz FULL HD DISPLAY',
      heroText: 'Smoother visuals. Reduced lag and ghosting. Every millisecond counts!',
      feat: [['NVIDIA GEFORCE RTX 5050', 'Powerful graphics for immersive gaming.'],
             ['82.23% SCREEN-TO-BODY RATIO', 'More screen. More game.'],
             ['FULL-SIZE 1-ZONE RGB BACKLIT KEYBOARD', 'Smooth keystrokes with a cool gaming look.']],
      nextHead: 'Collect your HP Matchday card!', nextCta: 'Head to Station 04'
    }
  }
};
