<template>
    <div>
        <div class="container">
            <div class="row">
                <div class="col">
                    <div class="btn-group" role="group">
                        <button
                            type="button"
                            class="btn btn-primary mr-2"
                            v-for="user in allusers"
                            :key="user.id"
                            @click="placeVideoCall(user.id, user.name)"
                        >
                            Call {{ user.name }}
                            <span class="badge badge-light">{{ getUserOnlineStatus(user.id) }}</span>
                        </button>
                    </div>
                </div>
            </div>
            <!-- Placing Video Call -->
            <div class="row mt-5" id="video-row">
                <div class="col-12 video-container" v-if="callPlaced">
                    <video
                        ref="userVideo"
                        muted
                        playsinline
                        autoplay
                        class="cursor-pointer"
                        :class="isFocusMyself ? 'user-video' : 'partner-video'"
                        @click="toggleCameraArea"
                    />
                    <video
                        ref="partnerVideo"
                        playsinline
                        autoplay
                        class="cursor-pointer"
                        :class="isFocusMyself ? 'partner-video' : 'user-video'"
                        v-if="videoCallParams.callAccepted"
                        @click="toggleCameraArea"
                    />
                    <div class="partner-video" v-else>
                        <div v-if="callPartner" class="column items-center q-pt-xl">
                            <div class="col q-gutter-y-md text-center">
                                <p class="q-pt-md">
                                    <strong>{{ callPartner }}</strong>
                                </p>
                                <p>calling...</p>
                            </div>
                        </div>
                    </div>
                    <div class="action-btns">
                        <button type="button" class="btn btn-info" @click="toggleMuteAudio">
                            {{ mutedAudio ? "Unmute" : "Mute" }}
                        </button>
                        <button type="button" class="btn btn-primary mx-4" @click="toggleMuteVideo">
                            {{ mutedVideo ? "Show Video" : "Hide Video" }}
                        </button>
                        <button type="button" class="btn btn-danger" @click="endCall">
                            End Call
                        </button>
                    </div>
                </div>
            </div>
            <!-- End of Placing Video Call -->

            <!-- Incoming Call -->
            <div class="row" v-if="incomingCallDialog">
                <div class="col">
                    <p>
                        Incoming Call From <strong>{{ callerDetails.name }}</strong>
                    </p>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-danger" data-dismiss="modal" @click="declineCall">
                            Decline
                        </button>
                        <button type="button" class="btn btn-success ml-5" @click="acceptCall">
                            Accept
                        </button>
                    </div>
                </div>
            </div>
            <!-- End of Incoming Call -->
        </div>
    </div>
</template>

<script>
import Peer from "simple-peer";
import axios from "axios";
import {getPermissions} from "../helpers";

export default {
    props: [
        "allusers",
        "authuserid",
        "turn_url",
        "turn_username",
        "turn_credential",
    ],
    data() {
        return {
            isFocusMyself: true,
            callPlaced: false,
            callPartner: null,
            mutedAudio: false,
            mutedVideo: false,
            videoCallParams: {
                users: [],
                stream: null,
                receivingCall: false,
                caller: null,
                callerSignal: null,
                callAccepted: false,
                channel: null,
                peer1: null,
                peer2: null,
            },
        };
    },

    mounted() {
        this.initializeChannel();
        this.initializeCallListeners();
    },
    computed: {
        incomingCallDialog() {
            return this.videoCallParams.receivingCall && this.videoCallParams.caller !== this.authuserid;
        },

        callerDetails() {
            if (this.videoCallParams.caller && this.videoCallParams.caller !== this.authuserid) {
                const incomingCaller = this.allusers.find(user => user.id === this.videoCallParams.caller);
                return incomingCaller ? {id: this.videoCallParams.caller, name: incomingCaller.name} : null;
            }
            return null;
        },
    },
    methods: {
        initializeChannel() {
            this.videoCallParams.channel = window.Echo.join("presence-video-channel")
                .here((users) => {
                    console.log("Users present in channel:", users);
                    this.videoCallParams.users = users;
                })
                .joining((user) => {
                    console.log("User joined channel:", user);
                    if (!this.videoCallParams.users.some(u => u.id === user.id)) {
                        this.videoCallParams.users.push(user);
                    }
                })
                .leaving((user) => {
                    console.log("User left channel:", user);
                    this.videoCallParams.users = this.videoCallParams.users.filter(u => u.id !== user.id);
                });
        },

        async getMediaPermission() {
            try {
                const stream = await getPermissions();
                this.videoCallParams.stream = stream;
                if (this.$refs.userVideo) {
                    this.$refs.userVideo.srcObject = stream;
                }
            } catch (error) {
                console.error("Error getting media permissions:", error);
            }
        },

        initializeCallListeners() {
            this.videoCallParams.channel.listen("StartVideoChat", ({data}) => {
                console.log("Received StartVideoChat event:", data);
                if (data.type === "incomingCall") {
                    const updatedSignal = {...data.signalData, sdp: `${data.signalData.sdp}\n`};
                    this.videoCallParams.receivingCall = true;
                    this.videoCallParams.caller = data.from;
                    if (data.signalData && data.signalData.sdp) { // Check for null or undefined
                        this.videoCallParams.callerSignal = updatedSignal;
                    } else {
                        console.error("Received null or undefined signalData.sdp");
                    }
                }
            });

        },

        async placeVideoCall(id, name) {
            this.callPlaced = true;
            this.callPartner = name;
            await this.getMediaPermission();
            this.videoCallParams.peer1 = new Peer({
                initiator: true,
                trickle: false,
                stream: this.videoCallParams.stream,
                config: {
                    iceServers: [
                        {
                            urls: "stun:stun.stunprotocol.org",
                        },
                        {
                            urls: "turn:127.0.0.1:3478",
                            username: this.turn_username,
                            credential: this.turn_credential,
                        },
                    ],
                },
            });
            console.log("Peer1:", this.videoCallParams.peer1);
            // this.videoCallParams.peer1.on("signal", (data) => {
            //     console.log("Signal data for call:", data);
            //     axios.post("/store-signal-data", {signal_data: data})
            //         .then(response => {
            //             const signalId = response.data.signal_id;
            //             axios.post("/video/call-user", {
            //                 user_to_call: id,
            //                 signal_id: signalId,
            //                 from: this.authuserid,
            //             }).catch((error) => console.error("Error calling user:", error));
            //         })
            //         .catch(error => console.error("Error storing signal data:", error));
            // });
            this.videoCallParams.peer1.on("signal", (data) => {
                console.log("Signal data for call:", data);
                if (data && data.sdp) {
                    axios.post("/video/upload-signal", {
                        user_to_call: id,
                        signal_data: data,
                        from: this.authuserid,
                    }).then(response => {
                        console.log("Signal data uploaded successfully:", response.data);

                        const signalUrl = response.data.url;
                        // Notify via Pusher with a URL to the signal data
                        axios.post("/video/notify", {
                            user_to_call: id,
                            signal_url: signalUrl,
                            from: this.authuserid,
                        }).catch((error) => console.error("Error notifying user:", error));
                    }).catch((error) => console.error("Error uploading signal data:", error));
                } else {
                    console.error("Signal data is null or missing sdp property:", data);
                }
            });


            this.videoCallParams.peer1.on("connect", () => {
                console.log("Peer connected");
            });

            this.videoCallParams.peer1.on("error", (err) => {
                console.error("Peer error:", err);
            });

            this.videoCallParams.peer1.on("close", () => {
                console.log("Call closed by caller");
            });





            this.videoCallParams.peer1.on("stream", (stream) => {
                if (this.$refs.partnerVideo) {
                    this.$refs.partnerVideo.srcObject = stream;
                }
            });

            this.videoCallParams.channel.listen("StartVideoChat", ({data}) => {
                console.log("Received StartVideoChat event during call:", data);
                if (data.type === "callAccepted") {
                    if (data.signal.renegotiate) {
                        console.log("Renegotiating");
                    }
                    if (data.signal.sdp) {
                        this.videoCallParams.callAccepted = true;
                        const updatedSignal = {...data.signal, sdp: `${data.signal.sdp}\n`};
                        this.videoCallParams.peer1.signal(updatedSignal);
                    }
                }
            });
        },

        async acceptCall() {
            this.callPlaced = true;
            this.videoCallParams.callAccepted = true;
            await this.getMediaPermission();
            this.videoCallParams.peer2 = new Peer({
                initiator: false,
                trickle: false,
                stream: this.videoCallParams.stream,
                config: {
                    iceServers: [
                        {
                            urls: "stun:stun.stunprotocol.org",
                        },
                        {
                            urls: process.env.turn_url,
                            username: process.env.turn_username,
                            credential: process.env.turn_credential,
                        },
                    ],
                },

            });

            this.videoCallParams.peer2.on("signal", (data) => {
                console.log("Signal data for accepting call:", data);
                axios.post("/video/accept-call", {
                    signal: data,
                    to: this.videoCallParams.caller,
                }).catch((error) => console.error("Error accepting call:", error));
            });

            this.videoCallParams.peer2.on("stream", (stream) => {
                if (this.$refs.partnerVideo) {
                    this.$refs.partnerVideo.srcObject = stream;
                }
            });

            this.videoCallParams.peer2.on("connect", () => {
                console.log("Peer connected");
            });

            this.videoCallParams.peer2.on("error", (err) => {
                console.error("Peer error:", err);
            });

            this.videoCallParams.peer2.on("close", () => {
                console.log("Call closed by accepter");
            });

            this.videoCallParams.peer2.signal(this.videoCallParams.callerSignal);
        },

        toggleCameraArea() {
            if (this.videoCallParams.callAccepted) {
                this.isFocusMyself = !this.isFocusMyself;
            }
        },

        getUserOnlineStatus(id) {
            return this.videoCallParams.users.some(user => user.id === id) ? "Online" : "Offline";
        },

        declineCall() {
            this.videoCallParams.receivingCall = false;
        },

        toggleMuteAudio() {
            const audioTrack = this.$refs.userVideo.srcObject.getAudioTracks()[0];
            if (audioTrack) {
                audioTrack.enabled = !audioTrack.enabled;
                this.mutedAudio = !audioTrack.enabled;
            }
        },

        toggleMuteVideo() {
            const videoTrack = this.$refs.userVideo.srcObject.getVideoTracks()[0];
            if (videoTrack) {
                videoTrack.enabled = !videoTrack.enabled;
                this.mutedVideo = !videoTrack.enabled;
            }
        },

        stopStreamedVideo(videoElem) {
            const stream = videoElem.srcObject;
            const tracks = stream.getTracks();
            tracks.forEach(track => track.stop());
            videoElem.srcObject = null;
        },

        endCall() {
            if (!this.mutedVideo) this.toggleMuteVideo();
            if (!this.mutedAudio) this.toggleMuteAudio();
            this.stopStreamedVideo(this.$refs.userVideo);

            if (this.authuserid === this.videoCallParams.caller) {
                this.videoCallParams.peer1.destroy();
            } else {
                this.videoCallParams.peer2.destroy();
            }

            this.videoCallParams.channel.pusher.channels.channels["presence-presence-video-channel"].disconnect();

            setTimeout(() => {
                this.callPlaced = false;
            }, 3000);
        },
    },
};
</script>

<style scoped>
#video-row {
    width: 700px;
    max-width: 90vw;
}

#incoming-call-card {
    border: 1px solid #0acf83;
}

.video-container {
    width: 700px;
    height: 500px;
    max-width: 90vw;
    max-height: 50vh;
    margin: 0 auto;
    border: 1px solid #0acf83;
    position: relative;
    box-shadow: 1px 1px 11px #9e9e9e;
    background-color: #fff;
}

.video-container .user-video {
    width: 30%;
    position: absolute;
    left: 10px;
    bottom: 10px;
    border: 1px solid #fff;
    border-radius: 6px;
    z-index: 2;
}

.video-container .partner-video {
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
}

.video-container .action-btns {
    position: absolute;
    bottom: 20px;
    left: 50%;
    margin-left: -50px;
    z-index: 3;
    display: flex;
    flex-direction: row;
}

/* Mobile Styles */
@media only screen and (max-width: 768px) {
    .video-container {
        height: 50vh;
    }
}
</style>
