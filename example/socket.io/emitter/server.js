const {Server} = require("socket.io"),
    httpServer = require("http").createServer(),
    logger = require('winston'),
    port = 1337;

const io = new Server(httpServer, {
    allowEIO3: true
})

// Logger config
logger.remove(logger.transports.Console);
logger.add(logger.transports.Console, {colorize: true, timestamp: true});
logger.info('SocketIO > listening on port ' + port);

io.on('connection', function (socket) {
    var nb = 0;

    logger.info('SocketIO > Connected socket ' + socket.id);

    socket.on('broadcast', function (message) {
        ++nb;
        logger.info('PocketIO broadcast > ' + JSON.stringify(message));
    });

    socket.on('disconnect', function () {
        logger.info('SocketIO : Received ' + nb + ' messages');
        logger.info('SocketIO > Disconnected socket ' + socket.id);
    });
});

httpServer.listen(port);