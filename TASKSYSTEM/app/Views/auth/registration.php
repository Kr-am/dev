<div class="container-fluid bg-secondary-subtle">
    <div class="vh-100">
        <div class="row align-items-center justify-content-center h-100">
            <div class="col-md-3">
                <div class="card card-shadow">
                    <div class="card-body text-center">
                        <form action="<?= $config['app_url'] ?>/auth/register" method="post">
                            <h1 class="h3 mb-3 fw-normal">Please sign up</h1>
                            <div class="py-2">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="floatingName" placeholder="John Doe" name="name">
                                    <label for="floatingName">Name</label>
                                </div>
                            </div>
                            <div class="py-2">
                                <div class="form-floating">
                                    <input type="email" class="form-control" id="floatingEmail" placeholder="name@example.com" name="email">
                                    <label for="floatingEmail">Email</label>
                                </div>
                            </div>
                            <div class="py-2">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="floatingInput" placeholder="username" name="username">
                                    <label for="floatingInput">Username</label>
                                </div>
                            </div>
                            <div class="py-2">
                                <div class="form-floating">
                                    <input type="password" class="form-control" id="floatingPassword" placeholder="Password" name="password">
                                    <label for="floatingPassword">Password</label>
                                </div>
                                <button class="btn btn-primary w-100 py-2 my-2" type="submit" name="submit">Sign up</button>
                                <p class="mt-3">Already have an account? <a href="<?=$config['app_url']?>/login">Sign in</a></p>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>