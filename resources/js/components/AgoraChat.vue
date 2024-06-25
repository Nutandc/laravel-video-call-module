<template>
    <main>
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <img src="img/agora-logo.png" alt="Agora Logo" class="img-fluid"/>
                </div>
            </div>
        </div>
        <div class="container my-5">
            <div class="row">
                <div class="col">
                    <div class="btn-group" role="group">
                        <button
                            type="button"
                            class="btn btn-primary mr-2"
                            v-for="user in allusers"
                            :key="user.id"
                            @click="placeCall(user.id, user.name)"
                        >
                            Call {{ user.name }}
                            <span class="badge badge-light">{{ getUserOnlineStatus(user.id) }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Incoming Call -->
            <div class="row my-5" v-if="incomingCall">
                <div class="col-12">
                    <p>Incoming Call From <strong>{{ incomingCaller }}</strong></p>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-danger" @click="declineCall">Decline</button>
                        <button type="button" class="btn btn-success ml-5" @click="acceptCall">Accept</button>
                    </div>
                </div>
            </div>
            <!-- End of Incoming Call -->
        </div>

        <section id="video-container" v-if="callPlaced">
            <div id="local-video"></div>
            <div id="remote-video"></div>

            <div class="action-btns">
                <button type="button" class="btn btn-info" @click="handleAudioToggle">
                    {{ mutedAudio ? "Unmute" : "Mute" }}
                </button>
                <button type="button" class="btn btn-primary mx-4" @click="handleVideoToggle">
                    {{ mutedVideo ? "Show Video" : "Hide Video" }}
                </button>
                <button type="button" class="btn btn-danger" @click="endCall">End Call</button>
            </div>
        </section>
    </main>
</template>

<script>
import axios from 'axios';
import {createClient, createMicrophoneAndCameraTracks} from 'agora-rtc-sdk-ng';

export default {
    name: "AgoraChat",
    props: ["authuser", "authuserid", "allusers", "agora_id"],
    data() {
        return {
            callPlaced: false,
            client: null,
            localTracks: {videoTrack: null, audioTrack: null},
            mutedAudio: false,
            mutedVideo: false,
            userOnlineChannel: null,
            onlineUsers: [],
            incomingCall: false,
            incomingCaller: "",
            agoraChannel: null,
        };
    },

    mounted() {
        this.initAgoraClient();
        this.initUserOnlineChannel();
        this.initUserOnlineListeners();
    },

    methods: {
        async initAgoraClient() {
            const appId = process.env.AGORA_APP_ID;
            const appCertificate = process.env.AGORA_APP_CERTIFICATE;

            try {
                this.client = createClient({ codec: 'h264', mode: 'rtc', appId, certificate: appCertificate });
                console.log('Agora client initialized with App ID:', appId);
            } catch (error) {
                console.error('Failed to initialize Agora client:', error);
                // Handle initialization error (e.g., display error message to user)
            }
        },
        initUserOnlineChannel() {
            this.userOnlineChannel = window.Echo.join("agora-online-channel");
        },

        initUserOnlineListeners() {
            this.userOnlineChannel.here((users) => {
                this.onlineUsers = users;
            });

            this.userOnlineChannel.joining((user) => {
                const joiningUserIndex = this.onlineUsers.findIndex(
                    (data) => data.id === user.id
                );
                if (joiningUserIndex < 0) {
                    this.onlineUsers.push(user);
                }
            });

            this.userOnlineChannel.leaving((user) => {
                const leavingUserIndex = this.onlineUsers.findIndex(
                    (data) => data.id === user.id
                );
                this.onlineUsers.splice(leavingUserIndex, 1);
            });

            this.userOnlineChannel.listen("MakeAgoraCall", ({data}) => {
                if (parseInt(data.userToCall) === parseInt(this.authuserid)) {
                    const callerIndex = this.onlineUsers.findIndex(
                        (user) => user.id === data.from
                    );
                    this.incomingCaller = this.onlineUsers[callerIndex]["name"];
                    this.incomingCall = true;
                    this.agoraChannel = data.channelName;
                }
            });
        },

        getUserOnlineStatus(id) {
            const onlineUserIndex = this.onlineUsers.findIndex((data) => data.id === id);
            return onlineUserIndex < 0 ? "Offline" : "Online";
        },

        async placeCall(id, calleeName) {
            try {
                const channelName = `${this.authuser}_${calleeName}`;
                const tokenRes = await this.generateToken(channelName);

                await axios.post("/agora/call-user", {
                    user_to_call: id,
                    channel_name: channelName,
                });

                await this.joinRoom(tokenRes, channelName);
            } catch (error) {
                console.error("Error placing call: ", error);
            }
        },

        async acceptCall() {
            try {
                const tokenRes = await this.generateToken(this.agoraChannel);
                console.log("Token: ", tokenRes);

                if (tokenRes) {
                    this.joinRoom(tokenRes, this.agoraChannel);
                    this.incomingCall = false;
                    this.callPlaced = true;
                } else {
                    console.error("Token response is null, empty, or missing token field.");
                }
            } catch (error) {
                console.error("Error accepting call: ", error);
            }
        },

        declineCall() {
            this.incomingCall = false;
        },

        // generateToken(channelName) {
        //     return axios.post("/agora/token", {
        //         channelName,
        //     });
        // },
        async generateToken(channelName) {
            try {
                const response = await axios.post("/agora/token", { channelName });
                console.log('Token:', response.data); // Log response data for verification
                return response.data; // Return entire response data object
            } catch (error) {
                console.error('Error generating token:', error);
                throw error; // Handle error (e.g., display error message to user)
            }
        },




        async joinRoom(token, channel) {
            try {
                await this.client.join(token, channel, null, this.authuser);
                console.log("Successfully joined channel");
                this.callPlaced = true;
                this.createLocalTracks();
                this.initializeAgoraListeners();
            } catch (error) {
                console.error("Failed to join channel: ", error);
                // Handle error (e.g., display error message to user)
            }
        },

        initializeAgoraListeners() {
            this.client.on("user-published", async (user, mediaType) => {
                await this.client.subscribe(user, mediaType);
                console.log("subscribe success");
                if (mediaType === 'video') {
                    user.videoTrack.play("remote-video");
                }
                if (mediaType === 'audio') {
                    user.audioTrack.play();
                }
            });

            this.client.on("user-unpublished", user => {
                const id = user.uid;
                if (user.videoTrack) {
                    user.videoTrack.stop();
                }
                if (user.audioTrack) {
                    user.audioTrack.stop();
                }
                console.log(`User ${id} unpublished`);
            });

            this.client.on("user-left", user => {
                const id = user.uid;
                if (user.videoTrack) {
                    user.videoTrack.stop();
                }
                if (user.audioTrack) {
                    user.audioTrack.stop();
                }
                console.log(`User ${id} left`);
            });
        },

        async createLocalTracks() {
            const [microphoneTrack, cameraTrack] = await createMicrophoneAndCameraTracks();
            this.localTracks.audioTrack = microphoneTrack;
            this.localTracks.videoTrack = cameraTrack;
            this.localTracks.videoTrack.play("local-video");
            await this.client.publish(Object.values(this.localTracks));
            console.log("Publish local tracks success");
        },

        async endCall() {
            await this.client.leave();
            this.localTracks.audioTrack.close();
            this.localTracks.videoTrack.close();
            this.callPlaced = false;
            console.log("Call ended");
        },

        handleAudioToggle() {
            if (this.mutedAudio) {
                this.localTracks.audioTrack.setEnabled(true);
                this.mutedAudio = false;
            } else {
                this.localTracks.audioTrack.setEnabled(false);
                this.mutedAudio = true;
            }
        },

        handleVideoToggle() {
            if (this.mutedVideo) {
                this.localTracks.videoTrack.setEnabled(true);
                this.mutedVideo = false;
            } else {
                this.localTracks.videoTrack.setEnabled(false);
                this.mutedVideo = true;
            }
        },
    },
};
</script>

<style scoped>
main {
    margin-top: 50px;
}

#video-container {
    width: 700px;
    height: 500px;
    max-width: 90vw;
    max-height: 50vh;
    margin: 0 auto;
    border: 1px solid #099dfd;
    position: relative;
    box-shadow: 1px 1px 11px #9e9e9e;
    background-color: #fff;
}

#local-video {
    width: 30%;
    height: 30%;
    position: absolute;
    left: 10px;
    bottom: 10px;
    border: 1px solid #fff;
    border-radius: 6px;
    z-index: 2;
    cursor: pointer;
}

#remote-video {
    width: 100%;
    height: 100%;
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    top: 0;
    z-index: 1;
    margin: 0;
    padding: 0;
    cursor: pointer;
}

.action-btns {
    position: absolute;
    bottom: 20px;
    left: 50%;
    margin-left: -50px;
    z-index: 3;
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
}

#login-form {
    margin-top: 100px;
}
</style>
