#version 330

in vec2 fragTexCoord;
in vec4 fragColor;
in vec3 fragLocalPosition;
in vec3 fragWorldPosition;
in vec3 fragWorldNormal;

uniform sampler2D texture0;
uniform vec4 colDiffuse;
uniform vec3 uViewPosition;

out vec4 finalColor;

void main()
{
    vec4 albedo = texture(texture0, fragTexCoord) * colDiffuse * fragColor;
    vec3 normal = normalize(fragWorldNormal);
    vec3 viewDirection = normalize(uViewPosition - fragWorldPosition);

    float fresnel = pow(1.0 - max(dot(normal, viewDirection), 0.0), 2.4);
    float edgeMask = smoothstep(
        0.45,
        0.9,
        max(max(abs(fragLocalPosition.x), abs(fragLocalPosition.y)), abs(fragLocalPosition.z))
    );

    vec3 shaded = albedo.rgb;
    shaded = mix(shaded, min(albedo.rgb * 1.18 + vec3(0.04), vec3(1.0)), fresnel * 0.12);
    shaded = mix(shaded, min(albedo.rgb * 1.14 + vec3(0.03), vec3(1.0)), edgeMask * 0.10);

    finalColor = vec4(shaded, albedo.a);
}