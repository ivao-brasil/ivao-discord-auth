<html>
    <title>Discord Auth Admin</title>
    <head>
        <script src="https://cdn.jsdelivr.net/npm/vue/dist/vue.js"></script>
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
        <script src="https://code.jquery.com/jquery-3.4.1.slim.min.js" integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js" integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    </head>

    <body>
        <div class="container" style="margin-top: 100px" id="app">
            <table class="table">
                <thead class="thead-light">
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Roles</th>
                        <th scope="col">Sulfix</th>
                        <th scole="col"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="role in roles" :key="role.hash">
                        <td>${role.hash}</td>
                        <td>
                            <select class="custom-select custom-select-lg mb-3" multiple v-model="role.id" size="4" >
                                <option v-for="roleAvailable in rolesAvailable" :value="roleAvailable.id" :key="roleAvailable.id">
                                    ${roleAvailable.name}
                                </option>
                            </select>
                        </td>
                        <td scope="row">
                            <textarea rows="4" type="text" class="form-control" v-model="role.sulfix">

                            </textarea>
                        </td>
                        <td>
                            <button type="button" class="btn btn-danger" @click="() => removeRole(role.hash)">
                                Remove
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button type="button" class="btn btn-primary" @click="addRole">
                Add
            </button>
            <button type="button" class="btn btn-success" @click="saveRoles">
                Save
            </button>
        </div>
    </body>
</html>

<script>
    const app = new Vue({
        el: '#app',
        data: {
            roles: [],
            rolesAvailable: []
        },
        delimiters: ['${','}'],
        methods: {
            getAvailableRoles() {
                axios.get('/api/discord/roles').then(result => {
                    this.rolesAvailable = result.data;
                }).catch(error => alert('ERROR TO GET ROLES'))
            },
            getRoles() {
                axios.get('/api/discord/actualRoles').then(result => {
                    this.roles = result.data;
                }).catch(error => alert('ERROR TO GET ACTUAL ROLES'))
            },
            saveRoles(){
                axios.post('/api/discord/saveRoles', this.roles).then(() => alert('ROLES SAVED')).catch(() => alert("ERROR TO SAVE RULES"));
            },
            addRole() {
                const roles = this.roles;
                roles.push({
                    sulfix: '',
                    id: [],
                    foreign: false,
                    hash: this.randomHash()
                })
                this.roles = roles;
            },
            randomHash() {
                return Math.floor(Math.random() * 16777215).toString(16)
            },
            removeRole(hash) {
                this.roles = this.roles.filter(role => role.hash !== hash);
            }
        },
        created: function () {
            this.getAvailableRoles();
            this.getRoles();
        }
    })
</script>
