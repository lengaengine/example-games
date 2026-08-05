#version 330

in vec2 fragTexCoord;
in vec4 fragColor;
in vec3 fragLocalPosition;
in vec3 fragWorldPosition;
in vec3 fragWorldNormal;

uniform sampler2D texture0;
uniform vec4 colDiffuse;
uniform vec3 uViewPosition;
uniform float uTime;

out vec4 finalColor;

void main()
{
    vec4 albedo = texture(texture0, fragTexCoord) * colDiffuse * fragColor;
    vec3 normal = normalize(fragWorldNormal);
    vec3 viewDirection = normalize(uViewPosition - fragWorldPosition);

    float heightGradient = smoothstep(-0.65, 0.7, fragLocalPosition.y);
    float fresnel = pow(1.0 - max(dot(normal, viewDirection), 0.0), 2.4);
    float faceCenter = 1.0 - smoothstep(
        0.24,
        0.92,
        max(abs(fragLocalPosition.x), abs(fragLocalPosition.z))
    );
    float topHighlight = pow(
        max(dot(normal, normalize(vec3(0.18, 0.97, 0.16))), 0.0),
        7.0
    );
    float edgeMask = smoothstep(
        0.45,
        0.9,
        max(max(abs(fragLocalPosition.x), abs(fragLocalPosition.y)), abs(fragLocalPosition.z))
    );
    float pulse = 0.5 + 0.5 * sin(uTime * 1.7 + fragLocalPosition.y * 2.2);

    vec3 shaded = albedo.rgb;
    shaded *= mix(0.84, 1.08, heightGradient);
    shaded = mix(shaded, min(albedo.rgb * 1.10 + vec3(0.02), vec3(1.0)), faceCenter * heightGradient * 0.10);
    shaded = mix(shaded, vec3(1.0), topHighlight * 0.18);
    shaded = mix(shaded, min(albedo.rgb * 1.18 + vec3(0.04), vec3(1.0)), fresnel * 0.12);
    shaded = mix(shaded, min(albedo.rgb * 1.14 + vec3(0.03), vec3(1.0)), edgeMask * 0.10);
    shaded = mix(shaded, min(albedo.rgb * 1.08 + vec3(0.02), vec3(1.0)), faceCenter * pulse * 0.04);

    finalColor = vec4(shaded, albedo.a);
}
